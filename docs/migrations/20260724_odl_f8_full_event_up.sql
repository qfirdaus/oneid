ALTER TABLE user_external_identity_event
    DROP CHECK chk_external_identity_event_type,
    ADD CONSTRAINT chk_external_identity_event_type CHECK (
        event_type IN (
            'PILOT_NEW','PILOT_ROLLED_BACK',
            'FULL_NEW','FULL_ROLLED_BACK'
        )
    );
