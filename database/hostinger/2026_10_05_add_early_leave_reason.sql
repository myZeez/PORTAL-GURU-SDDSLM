ALTER TABLE `teacher_attendances`
    ADD COLUMN `early_leave_reason` TEXT NULL AFTER `leave_ends_on`;
