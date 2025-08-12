ALTER TABLE branch
    MODIFY provider_id VARCHAR(64) NOT NULL;

CREATE UNIQUE INDEX uniq_branch_type_provider
    ON branch (branch_type_id, provider_id);
