ALTER TABLE user_login_mfa_challenges
    DROP CHECK chk_user_mfa_challenge_material,
    ADD CONSTRAINT chk_user_mfa_challenge_material CHECK (
        (factor_type = 'EMAIL_OTP' AND otp_hash IS NOT NULL AND destination_hmac IS NOT NULL)
        OR (factor_type = 'TOTP' AND otp_hash IS NULL AND destination_hmac IS NULL)
    );
