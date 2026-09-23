-- revert schema 225
-- script runner url config is removed
DELETE FROM config WHERE conf_name = 'script_runner_url';
UPDATE config SET conf_value = 224 WHERE conf_name = 'schema';
