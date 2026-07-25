# ML8A — Inventory and Document Contract

**Environment:** Local WSL
**Mode:** Read-only inventory
**Automatic translation:** Disabled
**Mutation statements:** 0
**Manifest digest:** `598e46cbb5e55fe72ae227be70fba7f7b2f59d9ed2ca6c966a7e35797fb66530`

## Result

Approved ML8A checkpoint merangkumi `149` document/surface identities. Selepas
release document v2.6.3 ditambah, current reconciled inventory ialah `150`.
Dokumen audit
multilingual dan laporan ML8A ini dikecualikan sebagai generated evidence untuk
mengelakkan manifest self-reference.

| Surface | Count |
|---|---:|
| Public document | 1 |
| FAQ surface | 2 |
| Administrator release UI | 1 |
| Release Markdown | 11 |
| Policy/design document | 2 |
| Internal technical document | 132 |

Integrity result:

- duplicate identity: `0`;
- missing target: `0`;
- blocking code: `0`; dan
- contract/characterization: PASS.

## Classification

| Classification | Count | Contract |
|---|---:|---|
| `BM_ONLY_EXPLICIT_FALLBACK_REQUIRED` | 1 | BM manual tersedia; English belum tersedia dan fallback mesti dinyatakan |
| `BM_ONLY_TRANSLATION_REQUIRED` | 13 | Dua FAQ surface dan 11 release documents memerlukan content workflow |
| `MIXED_TRANSLATION_REQUIRED` | 1 | Administrator Version Releases mempunyai mixed-language content |
| `REVIEW_REQUIRED` | 2 | Polisi penomboran dan standard reka bentuk e-mel memerlukan owner decision |
| `INTERNAL_TECHNICAL_INVARIANT` | 132 | Audit/runbook/technical evidence kekal canonical kecuali owner memperluas skop |

Jumlah item yang memerlukan translation/fallback/review decision ialah `17`.
Internal technical documents tidak dikira sebagai completed translation dan
tidak diterjemahkan secara automatik.

## Active user-facing findings

### Manual

- Active target: `public/public_docs/MANUAL_SALAM.pdf`;
- detected locale: BM;
- login route: `/#` melalui pautan Manual Pengguna;
- English equivalent: tidak ditemui; dan
- required behavior: explicit BM fallback sehingga English document diluluskan.

### FAQ

- public Login FAQ: `8` entries dalam `index.php`;
- authenticated User Dashboard FAQ: `8` entries dalam `page/dashboard.php`;
- kedua-duanya ialah inline BM content dengan source berasingan; dan
- English content serta single-source maintenance strategy belum diluluskan.

### Version Releases

- active Administrator release UI: `37` release entries dalam
  `admin/dashboard.php`;
- content semasa bercampur BM/English;
- approved ML8A checkpoint mempunyai `11` fail `docs/RELEASE_*.md`; current
  inventory mempunyai `12` selepas release document v2.6.3 ditambah; dan
- version number, date, code, reference serta technical identifier mesti kekal
  canonical.

### Policy and operational support

- `docs/VERSION_NUMBERING_POLICY.md`;
- `docs/ONEID_EMAIL_DESIGN_STANDARD.md`;
- kedua-duanya memerlukan keputusan sama ada bilingual atau canonical internal;
  dan
- baki `133` audit/runbook/README diklasifikasikan sebagai internal technical
  invariant untuk skop awal.

## Owner decisions required before ML8B

1. Luluskan `132` internal technical documents sebagai canonical invariant atau
   pilih subset yang wajib bilingual.
2. Tentukan sama ada dua FAQ surface akan dikekalkan berasingan atau dipusatkan
   kepada satu content source.
3. Luluskan penyediaan `8` FAQ BM/English dan aturan fallback.
4. Sediakan atau luluskan English manual PDF; tiada machine approval.
5. Tentukan release translation window: semua `37` entries atau release aktif
   tertentu.
6. Tentukan status bilingual dua policy/design documents.
7. Sahkan owner:
   - BM/English content: Firdaus, System Analyst;
   - security terminology: Firdaus, System Analyst/DBA.

## Boundary

ML8A tidak:

- menulis translation atau document record;
- mengubah PDF, FAQ, release note atau policy;
- menambah schema atau locale-aware document resolver;
- menganggap silent fallback sebagai completion;
- menterjemah External Sync atau Admin Step-Up;
- push ke Git; atau
- deploy ke staging/Production.

ML8B implementation memerlukan approval manifest dan document contract
berasingan.

## Owner approval and closure

Firdaus, System Analyst/DBA meluluskan manifest
`598e46cbb5e55fe72ae227be70fba7f7b2f59d9ed2ca6c966a7e35797fb66530`
melalui evidence `ONEID-ML8A-LOCAL-20260725-01`.

Approved decisions:

- `132` internal technical documents: canonical/invariant;
- dua FAQ surfaces: satu shared content source dengan `8` FAQ BM/English;
- missing English FAQ: explicit BM fallback;
- `MANUAL_SALAM.pdf`: BM kekal authoritative sementara English PDF belum
  tersedia;
- English manual fallback notice:
  “English manual is not yet available. The Bahasa Melayu version is
  provided.”;
- semua `37` active release entries: bilingual required;
- version, date, code, change reference, commit, correlation ID dan technical
  identifier: canonical/invariant;
- `VERSION_NUMBERING_POLICY.md`: bilingual required;
- `ONEID_EMAIL_DESIGN_STANDARD.md`: bilingual required; dan
- automatic approval bagi machine-generated translation: not allowed.

Content owner/reviewer BM dan English ialah Firdaus, System Analyst. Security
terminology reviewer ialah Firdaus, System Analyst/DBA.

**Decision:** ML8A PASS / CLOSED.

Closure ini tidak memberi authorization kepada ML8B implementation, Git push,
staging atau Production.
