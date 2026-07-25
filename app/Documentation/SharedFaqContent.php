<?php
declare(strict_types=1);

namespace OneId\App\Documentation;

final class SharedFaqContent
{
    public const HARD_FALLBACK = 'ms';

    /** @var array<string,array<string,array{question:string,answer:string}>> */
    private array $content;

    /**
     * @param array<string,array<string,array{question:string,answer:string}>>|null $content
     */
    public function __construct(?array $content = null)
    {
        $this->content = $content ?? self::approvedContent();
    }

    /**
     * @return array{
     *   requested_locale:string,
     *   effective_locale:string,
     *   fallback_used:bool,
     *   fallback_notice:?string,
     *   entries:array<int,array{id:string,question:string,answer:string}>
     * }
     */
    public function resolve(string $locale): array
    {
        $requested = in_array($locale, ['ms', 'en'], true) ? $locale : self::HARD_FALLBACK;
        $fallbackUsed = !isset($this->content[$requested]) || count($this->content[$requested]) !== 8;
        $effective = $fallbackUsed ? self::HARD_FALLBACK : $requested;
        $entries = [];

        foreach ($this->content[$effective] ?? [] as $id => $entry) {
            $entries[] = [
                'id' => $id,
                'question' => $entry['question'],
                'answer' => $entry['answer'],
            ];
        }

        return [
            'requested_locale' => $requested,
            'effective_locale' => $effective,
            'fallback_used' => $fallbackUsed,
            'fallback_notice' => $fallbackUsed
                ? 'English FAQ content is not yet available. The Bahasa Melayu version is provided.'
                : null,
            'entries' => $entries,
        ];
    }

    /** @return array<string,array<string,array{question:string,answer:string}>> */
    private static function approvedContent(): array
    {
        return [
            'ms' => [
                'what-is-oneid' => [
                    'question' => 'Apakah OneID@UPNM?',
                    'answer' => 'OneID@UPNM ialah platform Single Sign-On (SSO) yang memudahkan pengguna mengakses pelbagai sistem dengan satu log masuk. Sistem atau aplikasi yang belum diintegrasikan disediakan dalam bentuk pautan supaya masih boleh diakses melalui OneID@UPNM.',
                ],
                'eligible-users' => [
                    'question' => 'Siapakah yang boleh menggunakan OneID@UPNM?',
                    'answer' => 'Semua warga UPNM, iaitu staf yang berdaftar dalam Sistem Maklumat Staf dan pelajar yang berdaftar dalam Sistem Maklumat Pelajar.',
                ],
                'how-to-sign-in' => [
                    'question' => 'Bagaimanakah cara untuk log masuk ke OneID@UPNM?',
                    'answer' => 'Gunakan nombor staf atau nombor pelajar sebagai ID pengguna. Pengguna baharu perlu menggunakan fungsi Lupa Kata Laluan untuk menetapkan kata laluan pertama mengikut piawaian keselamatan.',
                ],
                'security' => [
                    'question' => 'Adakah sistem ini selamat?',
                    'answer' => 'Ya. OneID@UPNM menggunakan kawalan keselamatan termasuk token API, tamat masa sesi dan pengesahan akaun melalui e-mel rasmi UPNM untuk membantu melindungi identiti serta akses pengguna.',
                ],
                'multiple-devices' => [
                    'question' => 'Bolehkah saya log masuk pada lebih daripada satu peranti?',
                    'answer' => 'Ya. Anda boleh log masuk pada beberapa peranti. Demi keselamatan, sila log keluar daripada peranti yang tidak lagi digunakan.',
                ],
                'single-application-sign-out' => [
                    'question' => 'Jika saya log keluar daripada satu aplikasi, adakah saya akan log keluar daripada semua aplikasi?',
                    'answer' => 'Tidak. Log keluar daripada satu aplikasi hanya menamatkan sesi aplikasi tersebut. Sesi aplikasi lain yang diakses melalui OneID boleh kekal aktif.',
                ],
                'forgot-password' => [
                    'question' => 'Apakah yang perlu saya lakukan jika terlupa kata laluan?',
                    'answer' => 'Pilih Lupa Kata Laluan pada halaman log masuk. Sistem akan menghantar kod OTP ke e-mel rasmi UPNM anda untuk mengesahkan identiti sebelum kata laluan baharu ditetapkan.',
                ],
                'password-requirements' => [
                    'question' => 'Apakah syarat kata laluan yang dibenarkan?',
                    'answer' => 'Kata laluan mestilah sekurang-kurangnya 12 aksara dan mengandungi gabungan huruf besar, huruf kecil, nombor serta simbol khas.',
                ],
            ],
            'en' => [
                'what-is-oneid' => [
                    'question' => 'What is OneID@UPNM?',
                    'answer' => 'OneID@UPNM is a Single Sign-On (SSO) platform that lets users access multiple systems with one sign-in. Systems or applications that have not been integrated are provided as links so they remain accessible through OneID@UPNM.',
                ],
                'eligible-users' => [
                    'question' => 'Who can use OneID@UPNM?',
                    'answer' => 'All UPNM community members, namely staff registered in the Staff Information System and students registered in the Student Information System.',
                ],
                'how-to-sign-in' => [
                    'question' => 'How do I sign in to OneID@UPNM?',
                    'answer' => 'Use your staff number or student number as your user ID. New users must use Forgot Password to set their first password according to the security requirements.',
                ],
                'security' => [
                    'question' => 'Is the system secure?',
                    'answer' => 'Yes. OneID@UPNM uses security controls including API tokens, session timeouts and account verification through an official UPNM e-mail address to help protect user identities and access.',
                ],
                'multiple-devices' => [
                    'question' => 'Can I sign in on more than one device?',
                    'answer' => 'Yes. You may sign in on multiple devices. For security, please sign out from devices that are no longer in use.',
                ],
                'single-application-sign-out' => [
                    'question' => 'If I sign out from one application, will I be signed out from all applications?',
                    'answer' => 'No. Signing out from one application ends only that application session. Sessions for other applications accessed through OneID may remain active.',
                ],
                'forgot-password' => [
                    'question' => 'What should I do if I forget my password?',
                    'answer' => 'Select Forgot Password on the sign-in page. The system will send an OTP to your official UPNM e-mail address to verify your identity before you set a new password.',
                ],
                'password-requirements' => [
                    'question' => 'What are the password requirements?',
                    'answer' => 'A password must contain at least 12 characters and include a combination of uppercase letters, lowercase letters, numbers and special characters.',
                ],
            ],
        ];
    }
}
