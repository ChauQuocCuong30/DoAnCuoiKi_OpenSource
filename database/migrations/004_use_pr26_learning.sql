-- Only reconcile the generated lessons from the earlier local package.
-- Existing vocabulary, accounts, session snapshots, scores and review states remain intact.
SET @has_automatic_lessons = (
    SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'automatic_lessons'
);
SET @reconcile_pr26 = IF(
    @has_automatic_lessons > 0,
    'UPDATE lessons l JOIN automatic_lessons a ON a.lesson_id=l.id SET l.is_active=0',
    'SELECT 1 AS no_previous_automatic_lessons'
);
PREPARE reconcile_pr26_stmt FROM @reconcile_pr26;
EXECUTE reconcile_pr26_stmt;
DEALLOCATE PREPARE reconcile_pr26_stmt;
