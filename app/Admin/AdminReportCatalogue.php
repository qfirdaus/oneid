<?php

declare(strict_types=1);

namespace OneId\App\Admin;

final class AdminReportCatalogue
{
    /** @return array<string,array{label:string,icon:string,reports:array<int,array{key:string,name:string,description:string,status:string}>}> */
    public static function groups(): array
    {
        return [
            'executive' => ['label'=>'Executive Overview','icon'=>'fa-dashboard','reports'=>[
                ['key'=>'executive_summary','name'=>'OneID Executive Summary','description'=>'Ringkasan pengguna, aplikasi, akses dan sesi semasa.','status'=>'ready'],
                ['key'=>'security_summary','name'=>'Security & Session Summary','description'=>'Trend keselamatan, sesi dan tindakan pentadbir.','status'=>'planned'],
                ['key'=>'sync_summary','name'=>'Synchronisation Summary','description'=>'Ringkasan perubahan daripada semua sumber data.','status'=>'planned'],
            ]],
            'users' => ['label'=>'Users & Access','icon'=>'fa-users','reports'=>[
                ['key'=>'users_by_category','name'=>'Users by Category','description'=>'Pecahan akaun aktif dan tidak aktif bagi setiap kategori.','status'=>'ready'],
                ['key'=>'access_matrix','name'=>'Category Application Access Matrix','description'=>'Matriks kategori pengguna dan aplikasi yang dibenarkan.','status'=>'planned'],
                ['key'=>'access_exceptions','name'=>'Direct ACL & Blacklist Exceptions','description'=>'Akses terus dan sekatan yang berada di luar polisi kategori.','status'=>'planned'],
            ]],
            'applications' => ['label'=>'Applications & Production Readiness','icon'=>'fa-th-large','reports'=>[
                ['key'=>'application_readiness','name'=>'Application Production Readiness','description'=>'Status URL, SSO dan kelulusan production bagi setiap aplikasi.','status'=>'ready'],
                ['key'=>'application_acl_coverage','name'=>'Application ACL Coverage','description'=>'Aplikasi tanpa kategori akses atau liputan pengguna.','status'=>'planned'],
                ['key'=>'credential_rotation','name'=>'Site API Credential Rotation','description'=>'Versi dan umur credential tanpa mendedahkan secret.','status'=>'planned'],
            ]],
            'sessions' => ['label'=>'Sessions & Security','icon'=>'fa-shield','reports'=>[
                ['key'=>'session_activity','name'=>'Session Activity Summary','description'=>'Sesi aktif, tamat dan sebab penamatan mengikut tempoh.','status'=>'planned'],
                ['key'=>'device_summary','name'=>'Device & Browser Summary','description'=>'Pecahan sesi mengikut maklumat peranti yang direkod.','status'=>'planned'],
                ['key'=>'mfa_adoption','name'=>'MFA Adoption & Outcomes','description'=>'Liputan MFA dan hasil transaksi keselamatan.','status'=>'planned'],
            ]],
            'synchronisation' => ['label'=>'Synchronisation','icon'=>'fa-refresh','reports'=>[
                ['key'=>'sync_runs','name'=>'Synchronisation Run Summary','description'=>'Rekod sync, sumber, tempoh dan jumlah perubahan.','status'=>'planned'],
                ['key'=>'sync_changes','name'=>'Synchronisation Change Detail','description'=>'Akaun baharu, dikemas kini, dinyahaktif dan diaktifkan semula.','status'=>'planned'],
                ['key'=>'sync_exceptions','name'=>'Synchronisation Exceptions','description'=>'Akaun terlindung dan perubahan yang memerlukan perhatian.','status'=>'planned'],
            ]],
            'audit' => ['label'=>'Audit & Configuration','icon'=>'fa-history','reports'=>[
                ['key'=>'audit_activity','name'=>'System Audit Activity','description'=>'Aktiviti sistem mengikut event, pelaku dan masa.','status'=>'planned'],
                ['key'=>'configuration_changes','name'=>'Configuration Change History','description'=>'Perubahan konfigurasi dengan sebab dan correlation ID.','status'=>'planned'],
                ['key'=>'content_changes','name'=>'Metadata & Banner Change History','description'=>'Sejarah terjemahan metadata dan login banner.','status'=>'planned'],
            ]],
        ];
    }

    /** @return array{key:string,name:string,description:string,status:string,group:string}|null */
    public static function report(string $key): ?array
    {
        foreach (self::groups() as $groupKey => $group) {
            foreach ($group['reports'] as $report) {
                if (hash_equals($report['key'], $key)) {
                    return $report + ['group'=>$groupKey];
                }
            }
        }
        return null;
    }

    public static function isReady(string $key): bool
    {
        return (self::report($key)['status'] ?? '') === 'ready';
    }
}
