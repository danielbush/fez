ALTER TABLE %TABLE_PREFIX%author DROP COLUMN aut_function, DROP COLUMN aut_cv_link, DROP COLUMN aut_assessed, ADD COLUMN aut_publons_id VARCHAR(255) NULL AFTER aut_rid_last_updated; 