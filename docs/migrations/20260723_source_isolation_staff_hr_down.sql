-- Safe only before any STAFF_HR membership or event exists.
DELETE FROM external_source
WHERE source_code='STAFF_HR'
  AND NOT EXISTS (
      SELECT 1 FROM user_external_identity
      WHERE source_code='STAFF_HR'
  )
  AND NOT EXISTS (
      SELECT 1 FROM user_external_identity_event
      WHERE source_code='STAFF_HR'
  );
