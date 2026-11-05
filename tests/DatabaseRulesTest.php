<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class DatabaseRulesTest extends TestCase
{
    private PDO $pdo;
    private int $managerId;
    private int $personCounter = 0;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        $files = glob(dirname(__DIR__) . '/database/migrations/*.sql');
        sort($files);
        foreach ($files as $file) {
            $this->pdo->exec(file_get_contents($file));
        }
        $this->managerId = $this->createAccount('manager');
    }

    public function testAuditIsAppendOnly(): void
    {
        $this->pdo->exec("INSERT INTO audit (entity, entity_id, audit_action, username)
                          VALUES ('payment', 1, 'create', 'JP-1-27')");

        $this->assertRejected("UPDATE audit SET audit_action = 'other'", 'append-only');
        $this->assertRejected('UPDATE audit SET username = NULL', 'append-only');
        $this->assertRejected('DELETE FROM audit', 'append-only');

        // The only allowed change: anonymizing the username (RN-25)
        $this->pdo->exec("UPDATE audit SET username = 'ANON-0001'");
        $this->assertSame('ANON-0001', $this->pdo->query('SELECT username FROM audit')->fetchColumn());
    }

    public function testNumberAllowsAtMostFourHolders(): void
    {
        $game = $this->createPlaygame();
        $number = $this->createNumber($game, 2);
        for ($i = 0; $i < 4; $i++) {
            $this->assign($game, $number);
        }

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('at most 4 holders');
        $this->assign($game, $number);
    }

    public function testSamePersonCannotRepeatInsideANumber(): void
    {
        $game = $this->createPlaygame();
        $number = $this->createNumber($game, 2);
        $person = $this->createPerson();
        $sql = "INSERT INTO assignment (playgame_id, number_id, person_id, assignment_from, assignment_status)
                VALUES ($game, $number, $person, '2027-01-01', 'active')";

        $this->pdo->exec($sql);
        $this->expectException(PDOException::class);
        $this->pdo->exec($sql);
    }

    public function testDeliveredNumberIsLocked(): void
    {
        $game = $this->createPlaygame();
        $number = $this->createNumber($game, 2);
        $assignment = $this->assign($game, $number);
        $this->pdo->exec("INSERT INTO delivery (assignment_id, amount) VALUES ($assignment, 4000)");
        $this->pdo->exec("UPDATE delivery SET delivery_status = 'delivered' WHERE assignment_id = $assignment");

        $this->assertSame('delivered', $this->numberStatus($number));

        $other = $this->createPerson();
        $this->assertRejected("UPDATE assignment SET person_id = $other WHERE id = $assignment", 'locked');
        $this->assertRejected("UPDATE assignment SET assignment_status = 'leaving' WHERE id = $assignment", 'locked');
        $this->assertRejected("UPDATE numbers SET numbers_status = 'pending' WHERE id = $number", 'locked');
        $this->assertRejected("UPDATE delivery SET delivery_status = 'scheduled' WHERE assignment_id = $assignment", 'already made');
        $this->assertRejected(
            "INSERT INTO assignment (playgame_id, number_id, person_id, assignment_from)
             VALUES ($game, $number, $other, '2027-01-01')",
            'locked'
        );
    }

    public function testSharedNumberIsDeliveredOnlyWhenEveryHolderReceived(): void
    {
        $game = $this->createPlaygame();
        $number = $this->createNumber($game, 2);
        $first = $this->assign($game, $number);
        $second = $this->assign($game, $number);
        $this->pdo->exec("INSERT INTO delivery (assignment_id, amount) VALUES ($first, 2000), ($second, 2000)");

        $this->pdo->exec("UPDATE delivery SET delivery_status = 'delivered' WHERE assignment_id = $first");
        $this->assertSame('pending', $this->numberStatus($number));

        $this->pdo->exec("UPDATE delivery SET delivery_status = 'delivered' WHERE assignment_id = $second");
        $this->assertSame('delivered', $this->numberStatus($number));
    }

    public function testDrawOnlyExistsInDrawMode(): void
    {
        $game = $this->createPlaygame('manual');
        $this->assertRejected(
            "INSERT INTO random (playgame_id, created_by) VALUES ($game, {$this->managerId})",
            'draw mode'
        );
    }

    public function testDrawStepsOnlyWhileLiveAndNeverModified(): void
    {
        $game = $this->createPlaygame('draw');
        $number = $this->createNumber($game, 2);
        $this->pdo->exec("INSERT INTO random (playgame_id, created_by) VALUES ($game, {$this->managerId})");
        $draw = (int) $this->pdo->lastInsertId();
        $step = "INSERT INTO random_step (random_id, random_step_order, number_id) VALUES ($draw, 1, $number)";

        $this->assertRejected($step, 'not live');                 // still scheduled

        $this->pdo->exec("UPDATE random SET random_status = 'live' WHERE id = $draw");
        $this->pdo->exec($step);                                  // live: accepted

        $this->pdo->exec("UPDATE random SET random_status = 'finished' WHERE id = $draw");
        $this->assertRejected("UPDATE random SET random_status = 'live' WHERE id = $draw", 'repeated');
        $this->assertRejected('UPDATE random_step SET random_step_order = 9', 'cannot be modified');
    }

    public function testAssignmentModeCannotChangeOnceAssigned(): void
    {
        $game = $this->createPlaygame('draw');
        $number = $this->createNumber($game, 2);

        $this->pdo->exec("UPDATE playgame SET mode_assign = 'manual' WHERE id = $game"); // nothing assigned yet
        $this->assign($game, $number);
        $this->assertRejected("UPDATE playgame SET mode_assign = 'draw' WHERE id = $game", 'cannot change');
    }

    // ---- helpers ----------------------------------------------------------

    private function createPerson(): int
    {
        $n = ++$this->personCounter;
        $stmt = $this->pdo->prepare(
            "INSERT INTO person (type_document, doc_hash, doc_encrypted, full_name)
             VALUES ('rut', ?, 'enc', ?)"
        );
        $stmt->execute(["hash-$n", "Person $n"]);
        return (int) $this->pdo->lastInsertId();
    }

    private function createAccount(string $role): int
    {
        $personId = $this->createPerson();
        $stmt = $this->pdo->prepare(
            "INSERT INTO account (person_id, username, password_hash, account_role)
             VALUES (?, ?, 'x', ?)"
        );
        $stmt->execute([$personId, "U-$personId-27", $role]);
        return (int) $this->pdo->lastInsertId();
    }

    private function createPlaygame(string $mode = 'draw'): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO playgame (manager_id, playgame_name, fee, amount_numbers, mode_assign, start_date)
             VALUES (?, ?, 1000, 5, ?, '2027-01-01')"
        );
        $stmt->execute([$this->managerId, 'Game ' . uniqid(), $mode]);
        return (int) $this->pdo->lastInsertId();
    }

    private function createNumber(int $playgameId, int $position): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO numbers (playgame_id, position, numbers_period, payment_due_date, delivery_due_date)
             VALUES (?, ?, '2027-01-01', '2027-01-31', '2027-02-05')"
        );
        $stmt->execute([$playgameId, $position]);
        return (int) $this->pdo->lastInsertId();
    }

    private function assign(int $playgameId, int $numberId): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO assignment (playgame_id, number_id, person_id, assignment_from, assignment_status)
             VALUES (?, ?, ?, '2027-01-01', 'active')"
        );
        $stmt->execute([$playgameId, $numberId, $this->createPerson()]);
        return (int) $this->pdo->lastInsertId();
    }

    private function numberStatus(int $numberId): string
    {
        return (string) $this->pdo->query("SELECT numbers_status FROM numbers WHERE id = $numberId")->fetchColumn();
    }

    private function assertRejected(string $sql, string $message): void
    {
        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
            return;
        }
        $this->fail("The database accepted: $sql");
    }
}