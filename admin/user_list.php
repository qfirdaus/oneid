<?php
require_once __DIR__ . '/../lib/session_security.php';
oneid_start_secure_session();
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/SSO_IDP_INC.php';
require_once __DIR__ . '/../lib/request_security.php';
oneid_require_admin_page();
oneid_require_active_sso_page($operation);
oneid_require_admin_step_up($operation, 'ADMIN_ACCESS', false);

$categoryId = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);
if ($categoryId === false || $categoryId === null) {
    http_response_code(400);
    exit(oneid_translate('admin.user_list.invalid_category'));
}

$escape = static fn(mixed $value): string => htmlspecialchars(
    trim((string) $value),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
$categoryName = $escape($_GET['category_name'] ?? '');
$userlist = $operation->admin_get_specific_category_user_listing($categoryId);
$generatedAt = new DateTimeImmutable('now');
$reportReference = sprintf('ONEID-UC-%d-%s', $categoryId, $generatedAt->format('Ymd-His'));
?>
<!DOCTYPE html>
<html lang="<?=$escape(oneid_current_locale())?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?=$escape(oneid_translate('admin.user_list.title'))?> · OneID UPNM</title>
    <link href="../assetsM/css/sweetalert.css" rel="stylesheet" type="text/css">
    <link href="../dist/css/oneid-admin-session.css?v=20260806-1" rel="stylesheet" type="text/css">
    <style>
        :root {
            --ink: #10233f;
            --muted: #60728b;
            --line: #d8e2ee;
            --soft: #f3f7fb;
            --blue: #079bd3;
            --blue-dark: #0877a8;
            --orange: #ff5b2b;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #edf2f7;
            color: var(--ink);
            font-family: Inter, "Segoe UI", Arial, sans-serif;
            font-size: 14px;
            line-height: 1.45;
        }
        .report-shell { max-width: 1180px; margin: 28px auto; padding: 0 20px; }
        .screen-toolbar {
            display: flex; justify-content: space-between; align-items: center;
            gap: 16px; margin-bottom: 14px;
        }
        .screen-toolbar p { margin: 0; color: var(--muted); font-size: 13px; }
        .toolbar-actions { display: flex; gap: 9px; }
        .toolbar-button {
            display: inline-flex; align-items: center; justify-content: center;
            min-height: 40px; padding: 0 18px; border: 1px solid #b9d8e8;
            border-radius: 8px; background: #fff; color: var(--blue-dark);
            font: inherit; font-weight: 700; text-decoration: none; cursor: pointer;
        }
        .toolbar-button.primary { border-color: var(--blue); background: var(--blue); color: #fff; }
        .report-paper {
            overflow: hidden; background: #fff; border: 1px solid #dce5ef;
            border-radius: 12px; box-shadow: 0 14px 36px rgba(16, 35, 63, .10);
        }
        .brand-rule { height: 6px; background: linear-gradient(90deg, var(--orange), #ffb12b 32%, var(--blue) 72%, #173d79); }
        .report-content { padding: 34px 38px 30px; }
        .report-header {
            display: grid; grid-template-columns: auto 1fr auto; align-items: center;
            gap: 24px; padding-bottom: 24px; border-bottom: 1px solid var(--line);
        }
        .brand-logos { display: flex; align-items: center; gap: 14px; }
        .brand-logos img { display: block; width: auto; object-fit: contain; }
        .brand-logos .upnm-logo { height: 58px; }
        .brand-logos .oneid-logo { height: 48px; }
        .report-heading { border-left: 1px solid var(--line); padding-left: 24px; }
        .report-heading .eyebrow {
            margin: 0 0 4px; color: var(--blue-dark); font-size: 11px;
            font-weight: 800; letter-spacing: .13em; text-transform: uppercase;
        }
        .report-heading h1 { margin: 0; font-size: 25px; line-height: 1.2; }
        .report-heading p { margin: 6px 0 0; color: var(--muted); }
        .report-count {
            min-width: 112px; padding: 13px 16px; border: 1px solid #c8e5f2;
            border-radius: 9px; background: #f0f9fd; text-align: center;
        }
        .report-count strong { display: block; color: var(--blue-dark); font-size: 24px; line-height: 1; }
        .report-count span { display: block; margin-top: 5px; color: var(--muted); font-size: 10px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .report-meta {
            display: grid; grid-template-columns: 1.25fr 1fr 1fr; gap: 12px;
            margin: 22px 0 20px;
        }
        .meta-card { padding: 11px 14px; border-radius: 7px; background: var(--soft); }
        .meta-card span { display: block; color: var(--muted); font-size: 10px; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; }
        .meta-card strong { display: block; margin-top: 3px; font-size: 13px; overflow-wrap: anywhere; }
        .table-frame { overflow: hidden; border: 1px solid var(--line); border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        th {
            padding: 11px 12px; background: #163b64; color: #fff;
            font-size: 10px; letter-spacing: .07em; text-align: left; text-transform: uppercase;
        }
        td { padding: 12px; border-bottom: 1px solid #e5ebf2; vertical-align: top; overflow-wrap: anywhere; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        tbody tr:last-child td { border-bottom: 0; }
        .column-number { width: 5%; text-align: center; }
        .column-id { width: 11%; white-space: nowrap; }
        .column-name { width: 39%; }
        .column-description { width: 45%; }
        td.column-number { color: var(--muted); font-weight: 700; }
        td.column-id { font-variant-numeric: tabular-nums; font-weight: 700; }
        td.column-name { font-weight: 400; }
        .user-name { display: block; font-weight: 700; }
        .user-secondary {
            display: block; margin-top: 3px; color: var(--muted);
            font-size: 11px; font-weight: 400; line-height: 1.35;
        }
        td.column-description { color: #334b68; }
        .empty-state { padding: 36px 18px; color: var(--muted); text-align: center; }
        .report-footer {
            display: flex; justify-content: space-between; gap: 20px;
            margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--line);
            color: var(--muted); font-size: 10px;
        }
        .report-footer strong { color: var(--ink); }
        @page { size: A4 landscape; margin: 12mm; }
        @media print {
            html, body { background: #fff; font-size: 9.5pt; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .screen-toolbar { display: none !important; }
            .report-shell { max-width: none; margin: 0; padding: 0; }
            .report-paper { overflow: visible; border: 0; border-radius: 0; box-shadow: none; }
            .report-content { padding: 8mm 7mm 5mm; }
            .brand-rule { height: 4px; }
            .report-header { gap: 16px; padding-bottom: 15px; }
            .brand-logos .upnm-logo { height: 44px; }
            .brand-logos .oneid-logo { height: 37px; }
            .report-heading { padding-left: 16px; }
            .report-heading h1 { font-size: 18pt; }
            .report-meta { margin: 15px 0 14px; }
            .meta-card { padding: 8px 10px; }
            .table-frame { overflow: visible; }
            th { background: #163b64 !important; color: #fff !important; }
            td { padding: 8px 9px; }
            tbody tr { break-inside: avoid; page-break-inside: avoid; }
            tbody tr:nth-child(even) { background: #f3f6f9 !important; }
            .report-footer { margin-top: 14px; }
        }
        @media (max-width: 760px) {
            .report-shell { margin: 12px auto; padding: 0 10px; }
            .screen-toolbar { align-items: flex-start; flex-direction: column; }
            .report-content { padding: 24px 18px; }
            .report-header { grid-template-columns: 1fr; }
            .report-heading { border-left: 0; padding-left: 0; }
            .report-count { justify-self: start; }
            .report-meta { grid-template-columns: 1fr; }
            .table-frame { overflow-x: auto; }
            table { min-width: 760px; }
        }
    </style>
</head>
<body>
<main class="report-shell">
    <div class="screen-toolbar" aria-label="Report actions">
        <p><?=$escape(oneid_translate('admin.user_list.preview_help'))?></p>
        <div class="toolbar-actions">
            <a class="toolbar-button" href="./dashboard.php"><?=$escape(oneid_translate('admin.user_list.back'))?></a>
            <button class="toolbar-button primary" type="button" onclick="window.print()"><?=$escape(oneid_translate('admin.user_list.print'))?></button>
        </div>
    </div>

    <article class="report-paper" id="printThis">
        <div class="brand-rule"></div>
        <div class="report-content">
            <header class="report-header">
                <div class="brand-logos" aria-label="UPNM OneID">
                    <img class="upnm-logo" src="../img/logo_upnm_30.png" alt="UPNM 30 Tahun">
                    <img class="oneid-logo" src="../img/logo_oneid.png" alt="OneID">
                </div>
                <div class="report-heading">
                    <p class="eyebrow"><?=$escape(oneid_translate('admin.user_list.eyebrow'))?></p>
                    <h1><?=$escape(oneid_translate('admin.user_list.title'))?></h1>
                    <p><?=$escape(oneid_translate('admin.user_list.subtitle'))?></p>
                </div>
                <div class="report-count">
                    <strong><?=count($userlist)?></strong>
                    <span><?=$escape(oneid_translate('admin.user_list.total'))?></span>
                </div>
            </header>

            <section class="report-meta" aria-label="Report information">
                <div class="meta-card">
                    <span><?=$escape(oneid_translate('admin.user_list.category'))?></span>
                    <strong><?=$categoryName?></strong>
                </div>
                <div class="meta-card">
                    <span><?=$escape(oneid_translate('admin.user_list.generated'))?></span>
                    <strong><?=$escape($generatedAt->format('d/m/Y · H:i'))?></strong>
                </div>
                <div class="meta-card">
                    <span><?=$escape(oneid_translate('admin.user_list.reference'))?></span>
                    <strong><?=$escape($reportReference)?></strong>
                </div>
            </section>

            <div class="table-frame">
                <table id="export_this">
                    <thead>
                        <tr>
                            <th class="column-number">#</th>
                            <th class="column-id"><?=$escape(oneid_translate('admin.user_list.id'))?></th>
                            <th class="column-name"><?=$escape(oneid_translate('admin.user_list.name'))?></th>
                            <th class="column-description"><?=$escape(oneid_translate('admin.user_list.description'))?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($userlist === []) { ?>
                        <tr><td class="empty-state" colspan="4"><?=$escape(oneid_translate('admin.user_list.empty'))?></td></tr>
                    <?php } else { ?>
                        <?php foreach ($userlist as $index => $user) {
                            $isStaff = in_array((int) ($user['u_category'] ?? 0), [2, 3], true);
                            $displayId = trim((string) ($user[$isStaff ? 'data3' : 'data4'] ?? ''));
                            if ($displayId === '') {
                                $displayId = trim((string) ($user['data4'] ?? ''));
                            }
                            $secondaryName = trim((string) ($user['data7'] ?? ''));
                            $description = trim((string) ($user['data6'] ?? ''));
                        ?>
                        <tr>
                            <td class="column-number"><?=$index + 1?></td>
                            <td class="column-id"><?=$escape($displayId)?></td>
                            <td class="column-name">
                                <span class="user-name"><?=$escape($user['data1'] ?? '')?></span>
                                <?php if ($secondaryName !== '') { ?>
                                    <span class="user-secondary"><?=$escape($secondaryName)?></span>
                                <?php } ?>
                            </td>
                            <td class="column-description"><?=$escape($description)?></td>
                        </tr>
                        <?php } ?>
                    <?php } ?>
                    </tbody>
                </table>
            </div>

            <footer class="report-footer">
                <span><strong>OneID UPNM</strong> · <?=$escape(oneid_translate('admin.user_list.official_note'))?></span>
                <span><?=$escape($reportReference)?></span>
            </footer>
        </div>
    </article>
</main>
<script src="../vendors/bower_components/sweetalert/dist/sweetalert.min.js"></script>
<script>
window.OneIdAdminSessionConfig = <?=json_encode([
    'apiUrl' => APP_URL . '/lib/q_func.php',
    'csrfToken' => oneid_csrf_token(),
    'userDashboardUrl' => APP_URL . '/page/dashboard',
    'text' => [
        'warningTitle' => oneid_translate('admin.session.warning_title'),
        'securityEyebrow' => oneid_translate('admin.session.security_eyebrow'),
        'warningBody' => oneid_translate('admin.session.warning_body'),
        'stayConnected' => oneid_translate('admin.session.stay_connected'),
        'backToUser' => oneid_translate('admin.session.back_to_user'),
        'renewedTitle' => oneid_translate('admin.session.renewed_title'),
        'renewedBody' => oneid_translate('admin.session.renewed_body'),
        'ok' => oneid_translate('admin.session.ok'),
        'renewFailedTitle' => oneid_translate('admin.session.renew_failed_title'),
        'renewFailedBody' => oneid_translate('admin.session.renew_failed_body'),
        'tryAgain' => oneid_translate('admin.session.try_again'),
        'requestFailed' => oneid_translate('common.request_failed'),
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)?>;
</script>
<script src="../dist/js/oneid-admin-session.js?v=20260806-5"></script>
</body>
</html>
