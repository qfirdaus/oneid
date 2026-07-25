# ML8C Bilingual Manual, Releases and Policy Content Preview

**Environment:** Local WSL
**Authorization:** `ONEID-ML8C-LOCAL-20260725-01`
**Status sejarah Preview:** MANIFEST APPROVED / FULL ENGLISH PARITY REQUIRED
**Status semasa:** VERSION RELEASE ACTIVATION PASS / CLOSED
**English PDF publication:** DEFERRED BY OWNER
**Git, staging and Production:** NOT AUTHORIZED

> Full English changelog parity `217/217` kemudiannya diluluskan dan
> locale-aware Version Releases ditutup melalui evidence
> `ONEID-ML8C-ACTIVATE-LOCAL-20260725-01`. Keperluan Preview dalam dokumen ini
> ialah provenance sebelum activation dan bukan blocker semasa.

## Baseline and implementation

ML8C provides a deterministic read-only inventory and locale-aware read seam
for:

- one authoritative BM manual, `MANUAL_SALAM.pdf`;
- `37` active Version Release identities;
- `VERSION_NUMBERING_POLICY.md`; and
- `ONEID_EMAIL_DESIGN_STANDARD.md`.

The release parser reads the existing canonical Administrator release source.
Version and date form the stable identity boundary; change-item content is
digested without modification.

Preview evidence:

- active releases: `37`;
- canonical BM change items: `217`;
- duplicate/unresolved identities: `0 / 0`;
- blocking codes: `0`;
- manifest digest:
  `c00a94b674f9d8e0bff4007a9bb26afd75771b39b789d0bd77485f4507323086`.

## English content state

- The English manual source is an outline marked `REVIEW_REQUIRED`; no English
  PDF has been generated or published.
- English release summaries exist for all `37` identities in
  `ML8C_RELEASE_ENGLISH_DRAFT_REVIEW.md`.
- These summaries are draft assistance only. All `37` remain
  `REVIEW_REQUIRED` and approved English release count remains `0`.
- Full English changelog parity and explicit per-release owner decisions are
  required before locale activation.
- Both required policy documents now contain separate BM and English sections.
  Commands, code, versions and identifiers remain invariant.

## Fallback

Until owner approval:

- English User Manual requests display:
  `English manual is not yet available. The Bahasa Melayu version is provided.`
- English Version Release requests use an explicit owner-review notice and
  display authoritative BM release content.
- No missing content is silently counted as complete.

## Security and mutation boundary

The Preview has:

- `can_apply=false`;
- `can_publish_english_manual=false`;
- `automatic_translation_approval=false`; and
- `mutation_statements=0`.

It does not change authentication, authorization, ACL, session lifetime,
External Sync, Admin Step-Up, version identifiers or original release content.

## Owner-review gate

Before any live content activation, the owner must approve:

1. the exact ML8C manifest digest;
2. all `37` release identities and English content decisions;
3. the English manual content and final PDF separately;
4. bilingual parity of the two policy documents; and
5. explicit fallback and rollback behaviour.

Live Apply, English PDF publication, Git push, staging and Production require
separate authorization.

## Owner manifest approval

Firdaus, System Analyst/DBA approved manifest
`c00a94b674f9d8e0bff4007a9bb26afd75771b39b789d0bd77485f4507323086`
through evidence `ONEID-ML8C-LOCAL-20260725-01`.

Approved decisions:

- all `37` release identities and their canonical version/date values: ACCEPT;
- `37` English summaries: ACCEPT FOR CONTENT DEVELOPMENT;
- all `217` full English changelog items: remain `REVIEW_REQUIRED`;
- BM `MANUAL_SALAM.pdf`: authoritative official manual;
- English manual outline: ACCEPT FOR CONTENT DEVELOPMENT;
- English PDF publication: NOT APPROVED;
- explicit manual fallback: ACCEPT;
- both bilingual policy/design sections: ACCEPT;
- code, commands, versions, OTP values and technical identifiers: invariant;
- original BM content must remain unchanged; and
- automatic translation approval remains prohibited.

**Decision:** manifest and current content decisions APPROVED. Full English
changelog development may proceed, but locale activation remains disabled until
all `217` items have full parity and explicit owner review.

English PDF publication, live activation, Git push, staging and Production
remain unauthorized.

## Full English changelog draft Preview

Draft assistance has now produced a paired BM/English review catalogue at
`storage/generated/ml8c_release_english_draft.json`.

Preview result:

- releases `37`;
- changelog item pairs `217`;
- empty items `0`;
- canonical BM source mismatches `0`;
- duplicate item identities `0`;
- HTML tag mismatches `0`;
- `<code>` token mismatches `0`;
- blocking codes `0`;
- every item remains `REVIEW_REQUIRED`;
- Apply/activation/automatic approval `false/false/false`; and
- draft manifest digest:
  `908b16565a1ea5a676b636bee543bbd384564add1a8fb6a6fd65884efa8125f8`.

This is content completeness, not linguistic approval. The owner must manually
review wording and meaning for all `217` English items before they can become an
approved locale catalogue.

## Full English content approval and dormant repository

Owner evidence `ONEID-ML8C-CHANGELOG-LOCAL-20260725-01` accepts all `217`
English changelog items bound to digest
`908b16565a1ea5a676b636bee543bbd384564add1a8fb6a6fd65884efa8125f8`.

A dormant `ApprovedReleaseCatalogue` now provides:

- BM/English release parity `37/37`;
- BM/English changelog parity `217/217`;
- invariant release identity, version and date;
- exact digest and evidence binding;
- English resolution without silent fallback; and
- zero database mutation.

## Controlled Local WSL activation

Authorization `ONEID-ML8C-ACTIVATE-LOCAL-20260725-01` activates the approved
repository on `admin/dashboard.php` for Local WSL only.

- locale `ms` displays the embedded canonical BM release catalogue;
- locale `en` displays the exact approved English catalogue;
- release identity, version and date remain invariant;
- release heading, current/latest labels and date locale follow BM/English;
- digest/count/catalogue failure blocks English as a whole;
- failure displays an explicit notice and retains the canonical BM catalogue;
- partial English presentation is not allowed; and
- no database mutation is introduced.

English User Manual publication, Git push, staging and Production remain
unauthorized.

## Local activation observation and closure

Firdaus, System Analyst/DBA completed Local WSL observation under evidence
`ONEID-ML8C-ACTIVATE-LOCAL-20260725-01`.

Verified PASS:

- BM and English Version Release display;
- release parity `37/37` and changelog parity `217/217`;
- invariant version numbers and release dates;
- localized Current/Latest/Release labels and date formatting;
- locale preference persistence;
- accordion and accessibility behaviour;
- canonical BM fallback readiness;
- partial English content detected `0`;
- original BM content unchanged;
- authentication, authorization and ACL regression;
- External Sync and Admin Step-Up boundaries; and
- critical or security defects `0`.

**Decision:** ML8C Version Release activation PASS / CLOSED.

English User Manual remains deferred. Git push, staging and Production remain
unauthorized.

## English User Manual owner disposition

The owner has confirmed that the English User Manual content and PDF will be
prepared later by the responsible team. No English PDF is planned for the
current multilingual scope.

Current policy:

- `MANUAL_SALAM.pdf` remains the only official and authoritative User Manual;
- English locale displays the approved explicit notice:
  `English manual is not yet available. The Bahasa Melayu version is provided.`;
- the BM PDF may be opened only after that availability is made clear;
- no silent fallback;
- no placeholder or machine-generated English PDF;
- no English manual publication commitment is inferred; and
- future English content/PDF work requires a new content-review and publication
  authorization.

**Disposition:** ENGLISH USER MANUAL DEFERRED BY OWNER / NOT A CURRENT BLOCKER.
