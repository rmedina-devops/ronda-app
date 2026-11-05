-- RNF-02 / RN-22: audit is append-only.
-- The only allowed UPDATE replaces the username with an anonymous code (RN-25).
CREATE TRIGGER audit_block_update
BEFORE UPDATE ON audit
WHEN NOT (
        NEW.id           = OLD.id
    AND NEW.entity       IS OLD.entity
    AND NEW.entity_id    IS OLD.entity_id
    AND NEW.audit_action IS OLD.audit_action
    AND NEW.value_old    IS OLD.value_old
    AND NEW.value_new    IS OLD.value_new
    AND NEW.created_at   IS OLD.created_at
    AND COALESCE(NEW.username LIKE 'ANON-%', 0)
)
BEGIN
    SELECT RAISE(ABORT, 'audit is append-only');
END;

CREATE TRIGGER audit_block_delete
BEFORE DELETE ON audit
BEGIN
    SELECT RAISE(ABORT, 'audit is append-only');
END;

-- RN-04: a number has at most 4 holders.
CREATE TRIGGER assignment_max_four_insert
BEFORE INSERT ON assignment
WHEN NEW.number_id IS NOT NULL
 AND NEW.assignment_status <> 'ended'
 AND (SELECT COUNT(*) FROM assignment
       WHERE number_id = NEW.number_id AND assignment_status <> 'ended') >= 4
BEGIN
    SELECT RAISE(ABORT, 'a number can have at most 4 holders');
END;

CREATE TRIGGER assignment_max_four_update
BEFORE UPDATE OF number_id, assignment_status ON assignment
WHEN NEW.number_id IS NOT NULL
 AND NEW.assignment_status <> 'ended'
 AND (SELECT COUNT(*) FROM assignment
       WHERE number_id = NEW.number_id
         AND assignment_status <> 'ended'
         AND id <> NEW.id) >= 4
BEGIN
    SELECT RAISE(ABORT, 'a number can have at most 4 holders');
END;

-- RN-14: a delivered number is locked (no new holders, no swaps, no exits).
CREATE TRIGGER assignment_locked_insert
BEFORE INSERT ON assignment
WHEN NEW.number_id IS NOT NULL
 AND (SELECT numbers_status FROM numbers WHERE id = NEW.number_id) = 'delivered'
BEGIN
    SELECT RAISE(ABORT, 'delivered number is locked');
END;

CREATE TRIGGER assignment_locked_update
BEFORE UPDATE ON assignment
WHEN OLD.number_id IS NOT NULL
 AND (SELECT numbers_status FROM numbers WHERE id = OLD.number_id) = 'delivered'
 AND (
        NEW.number_id IS NOT OLD.number_id
     OR NEW.person_id <> OLD.person_id
     OR (NEW.assignment_status = 'leaving' AND OLD.assignment_status <> 'leaving')
     OR (NEW.assignment_status = 'ended'   AND OLD.assignment_status <> 'ended'
         AND (SELECT playgame_status FROM playgame WHERE id = OLD.playgame_id) <> 'finished')
 )
BEGIN
    SELECT RAISE(ABORT, 'delivered number is locked');
END;

CREATE TRIGGER numbers_no_revert
BEFORE UPDATE ON numbers
WHEN OLD.numbers_status = 'delivered'
 AND (NEW.numbers_status <> 'delivered' OR NEW.position <> OLD.position)
BEGIN
    SELECT RAISE(ABORT, 'delivered number is locked');
END;

CREATE TRIGGER delivery_no_revert
BEFORE UPDATE ON delivery
WHEN OLD.delivery_status = 'delivered'
 AND (NEW.delivery_status <> 'delivered'
      OR NEW.amount <> OLD.amount
      OR NEW.assignment_id <> OLD.assignment_id)
BEGIN
    SELECT RAISE(ABORT, 'delivery already made');
END;

-- A number becomes 'delivered' when every active holder's delivery is delivered.
CREATE TRIGGER number_mark_delivered
AFTER UPDATE OF delivery_status ON delivery
WHEN NEW.delivery_status = 'delivered'
BEGIN
    UPDATE numbers SET numbers_status = 'delivered'
    WHERE id = (SELECT number_id FROM assignment WHERE id = NEW.assignment_id)
      AND numbers_status <> 'delivered'
      AND NOT EXISTS (
            SELECT 1
            FROM assignment a
            LEFT JOIN delivery d ON d.assignment_id = a.id
            WHERE a.number_id = (SELECT number_id FROM assignment WHERE id = NEW.assignment_id)
              AND a.assignment_status <> 'ended'
              AND (d.id IS NULL OR d.delivery_status <> 'delivered')
      );
END;

-- RN-26 to RN-30: the draw (tables `random` and `random_step`).
CREATE TRIGGER random_only_draw_mode
BEFORE INSERT ON random
WHEN (SELECT mode_assign FROM playgame WHERE id = NEW.playgame_id) <> 'draw'
BEGIN
    SELECT RAISE(ABORT, 'a draw only exists in draw mode');
END;

CREATE TRIGGER random_no_repeat
BEFORE UPDATE ON random
WHEN OLD.random_status = 'finished' AND NEW.random_status <> 'finished'
BEGIN
    SELECT RAISE(ABORT, 'a finished draw is not repeated');
END;

CREATE TRIGGER random_step_only_live
BEFORE INSERT ON random_step
WHEN (SELECT random_status FROM random WHERE id = NEW.random_id) <> 'live'
BEGIN
    SELECT RAISE(ABORT, 'draw is not live');
END;

CREATE TRIGGER random_step_immutable
BEFORE UPDATE ON random_step
BEGIN
    SELECT RAISE(ABORT, 'draw steps cannot be modified');
END;

-- RN-30: the assignment mode cannot change once assignment has started.
CREATE TRIGGER playgame_mode_locked
BEFORE UPDATE OF mode_assign ON playgame
WHEN NEW.mode_assign <> OLD.mode_assign
 AND (
        OLD.playgame_status <> 'draft'
     OR EXISTS (SELECT 1 FROM assignment WHERE playgame_id = OLD.id AND number_id IS NOT NULL)
     OR EXISTS (SELECT 1 FROM random WHERE playgame_id = OLD.id)
 )
BEGIN
    SELECT RAISE(ABORT, 'assignment mode cannot change once assignment started');
END;