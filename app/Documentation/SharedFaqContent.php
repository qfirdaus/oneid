<?php
declare(strict_types=1);

namespace OneId\App\Documentation;

final class SharedFaqContent
{
    public const HARD_FALLBACK = 'ms';
    private const EXPECTED_ENTRY_COUNT = 11;

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
        $fallbackUsed = !isset($this->content[$requested])
            || count($this->content[$requested]) !== self::EXPECTED_ENTRY_COUNT;
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
                    'answer' => <<<'TEXT'
OneID@UPNM ialah portal identiti digital dan Single Sign-On (SSO) UPNM. Ia menyediakan satu tempat untuk pengguna melihat aplikasi yang telah diberikan kepada akaun mereka dan, bagi aplikasi Full SSO, meneruskan akses tanpa perlu memasukkan semula kata laluan OneID.

Aplikasi yang belum mempunyai integrasi penuh masih boleh disenaraikan sebagai Non-SSO atau pautan terus. OneID membantu pengguna mencari aplikasi tersebut, tetapi aplikasi berkenaan mungkin meminta akaun atau kaedah log masuknya sendiri.
TEXT,
                ],
                'eligible-users' => [
                    'question' => 'Siapakah yang boleh menggunakan OneID@UPNM?',
                    'answer' => <<<'TEXT'
OneID@UPNM disediakan untuk warga UPNM yang mempunyai rekod aktif, termasuk staf dalam Sistem Maklumat Staf dan pelajar dalam Sistem Maklumat Pelajar. Akaun lain yang dibenarkan oleh UPNM boleh diwujudkan atau diuruskan oleh pentadbir mengikut keperluan rasmi.

Maklumat akses bergantung pada status akaun, kategori pengguna dan aplikasi yang telah diberikan. Jika rekod anda baru didaftarkan, berubah atau tidak aktif, kemas kini mungkin perlu diselaraskan daripada sistem sumber sebelum akses dipaparkan dengan betul.
TEXT,
                ],
                'how-to-sign-in' => [
                    'question' => 'Bagaimana cara log masuk?',
                    'answer' => <<<'TEXT'
Pada halaman log masuk, masukkan ID pengguna OneID anda—kebiasaannya nombor staf atau nombor pelajar—bersama kata laluan OneID. Jika pilihan MyDigital ID dipaparkan, anda juga boleh memilihnya dan melengkapkan pengesahan pada perkhidmatan MyDigital ID.

Pengguna baharu yang belum mempunyai kata laluan OneID boleh menggunakan Lupa Kata Laluan untuk membuat pengesahan melalui OTP e-mel. Pengguna yang mula masuk melalui MyDigital ID mungkin diminta menetapkan kata laluan OneID selepas identiti berjaya dipadankan.
TEXT,
                ],
                'mydigitalid' => [
                    'question' => 'Bagaimana log masuk MyDigital ID berfungsi?',
                    'answer' => <<<'TEXT'
Pilih “Log masuk dengan MyDigital ID” jika pilihan itu tersedia. Anda akan dibawa ke aliran pengesahan MyDigital ID dan kemudian dikembalikan ke OneID. OneID hanya membenarkan akses apabila identiti tersebut berjaya dipadankan dengan akaun OneID yang aktif.

Jika padanan tidak berjaya, permintaan telah tamat tempoh atau perkhidmatan MyDigital ID tidak tersedia, kembali ke halaman log masuk dan gunakan ID pengguna serta kata laluan OneID. Anda juga boleh cuba akaun MyDigital ID lain jika pilihan tersebut diberikan.
TEXT,
                ],
                'mfa' => [
                    'question' => 'Mengapa saya diminta membuat pengesahan tambahan?',
                    'answer' => <<<'TEXT'
OneID boleh meminta Multi-Factor Authentication (MFA) selepas kata laluan disahkan. Bergantung pada polisi keselamatan akaun atau kategori pengguna, pengesahan boleh menggunakan OTP yang dihantar ke e-mel berdaftar atau kod daripada Microsoft Authenticator.

Masukkan kod hanya pada halaman rasmi OneID dan jangan berkongsi OTP, kod Authenticator atau kunci persediaan dengan sesiapa. Jika faktor Authenticator hilang atau tidak boleh digunakan, ikuti pilihan pemulihan yang dipaparkan atau hubungi khidmat sokongan OneID.
TEXT,
                ],
                'application-access' => [
                    'question' => 'Apakah perbezaan Full SSO dan Non-SSO?',
                    'answer' => <<<'TEXT'
Aplikasi Full SSO menerima pengesahan daripada OneID. Apabila anda memilih “Log masuk”, OneID mengeluarkan akses untuk aplikasi tersebut dan kebiasaannya anda tidak perlu menaip semula kata laluan OneID.

Aplikasi Non-SSO atau pautan terus belum menggunakan aliran SSO penuh. OneID akan membuka alamat aplikasi, tetapi aplikasi itu mungkin meminta kelayakan berasingan. Hanya aplikasi yang diberikan kepada akaun anda akan dipaparkan; akses akhir masih tertakluk pada polisi aplikasi destinasi.
TEXT,
                ],
                'security' => [
                    'question' => 'Bagaimana OneID melindungi akaun saya?',
                    'answer' => <<<'TEXT'
OneID menggunakan beberapa lapisan kawalan seperti sambungan selamat, kata laluan yang disimpan secara terlindung, token SSO, tamat masa sesi, OTP atau MFA apabila diwajibkan, serta rekod audit untuk tindakan keselamatan. Akses pentadbir yang sensitif turut memerlukan pengesahan tambahan.

Keselamatan juga bergantung pada pengguna. Pastikan alamat laman adalah domain rasmi UPNM, gunakan kata laluan unik, jangan kongsi kod pengesahan, dan log keluar pada komputer awam. Jika menerima permintaan atau notifikasi yang tidak dikenali, hentikan tindakan dan hubungi khidmat sokongan OneID.
TEXT,
                ],
                'multiple-devices' => [
                    'question' => 'Bolehkah saya log masuk pada beberapa peranti?',
                    'answer' => <<<'TEXT'
Ia bergantung pada polisi multiple-session yang ditetapkan oleh pentadbir. Jika beberapa sesi dibenarkan, anda boleh mempunyai lebih daripada satu sesi aktif. Jika polisi itu dimatikan, log masuk baharu boleh menyebabkan token atau sesi lama tidak lagi sah dan peranti lama perlu log masuk semula.

Setiap sesi juga tertakluk pada had tidak aktif dan had maksimum keseluruhan. Tamatkan sesi pada peranti yang tidak digunakan dan jangan biarkan akaun terbuka pada peranti awam atau yang dikongsi.
TEXT,
                ],
                'single-application-sign-out' => [
                    'question' => 'Adakah satu tindakan log keluar menutup semua aplikasi?',
                    'answer' => <<<'TEXT'
Tidak semestinya. Log keluar daripada aplikasi destinasi biasanya hanya menamatkan sesi aplikasi itu dan tidak secara automatik menutup portal OneID atau aplikasi lain.

Log keluar daripada OneID akan menamatkan sesi dan token OneID semasa. Namun, aplikasi destinasi yang telah mewujudkan sesi sendiri mungkin kekal terbuka sehingga anda log keluar daripada aplikasi tersebut atau sesinya tamat. Untuk komputer awam, log keluar daripada aplikasi sensitif, log keluar daripada OneID dan tutup semua tetingkap pelayar.
TEXT,
                ],
                'forgot-password' => [
                    'question' => 'Apa perlu dibuat jika terlupa kata laluan?',
                    'answer' => <<<'TEXT'
Pilih Lupa Kata Laluan pada halaman log masuk, masukkan ID pengguna dan ikut arahan yang dipaparkan. Jika akaun layak, kod OTP enam digit akan dihantar ke alamat e-mel yang berdaftar. Masukkan OTP yang masih sah, kemudian tetapkan dan sahkan kata laluan baharu.

Jangan berkongsi OTP dengan sesiapa. Selepas kata laluan berjaya ditetapkan semula, token OneID lama akan dibatalkan dan anda perlu log masuk semula. Jika e-mel tidak diterima, maklumat e-mel tidak lagi tepat atau akaun tidak dapat disahkan, hubungi khidmat sokongan OneID melalui maklumat hubungan pada halaman log masuk.
TEXT,
                ],
                'password-requirements' => [
                    'question' => 'Apakah syarat kata laluan OneID?',
                    'answer' => <<<'TEXT'
Kata laluan mesti mempunyai sekurang-kurangnya 12 aksara serta mengandungi sekurang-kurangnya satu huruf besar, satu huruf kecil, satu nombor dan satu simbol. Kata laluan tidak boleh terlalu umum atau mudah dijangka dan tidak boleh mengandungi ID pengguna anda.

Gunakan kata laluan unik yang tidak digunakan pada sistem lain. OneID juga menghalang penggunaan semula kata laluan semasa dan kata laluan terkini dalam sejarah akaun. Selepas menukar atau menetapkan semula kata laluan, sesi lain mungkin ditamatkan dan anda mungkin diminta log masuk semula.
TEXT,
                ],
            ],
            'en' => [
                'what-is-oneid' => [
                    'question' => 'What is OneID@UPNM?',
                    'answer' => <<<'TEXT'
OneID@UPNM is UPNM's digital identity and Single Sign-On (SSO) portal. It gives users one place to view the applications assigned to their account and, for Full SSO applications, continue without entering their OneID password again.

Applications without full integration may still be listed as Non-SSO or direct links. OneID helps users find those applications, but the destination application may require its own account or sign-in method.
TEXT,
                ],
                'eligible-users' => [
                    'question' => 'Who can use OneID@UPNM?',
                    'answer' => <<<'TEXT'
OneID@UPNM is provided to UPNM community members with active records, including staff in the Staff Information System and students in the Student Information System. Other accounts authorised by UPNM may be created or managed by an administrator for official requirements.

Access depends on the account status, user category and applications assigned to it. If your record is newly registered, changed or inactive, information may need to be synchronised from the source system before access appears correctly.
TEXT,
                ],
                'how-to-sign-in' => [
                    'question' => 'How do I sign in?',
                    'answer' => <<<'TEXT'
On the sign-in page, enter your OneID user ID—normally your staff number or student number—and your OneID password. If the MyDigital ID option is displayed, you may instead select it and complete verification through the MyDigital ID service.

New users without a OneID password can use Forgot Password and verify their identity using an email OTP. Users signing in through MyDigital ID for the first time may be asked to set a OneID password after their identity has been matched successfully.
TEXT,
                ],
                'mydigitalid' => [
                    'question' => 'How does MyDigital ID sign-in work?',
                    'answer' => <<<'TEXT'
Select “Sign in with MyDigital ID” when the option is available. You will be redirected through the MyDigital ID verification flow and then returned to OneID. Access is allowed only when that identity can be matched to an active OneID account.

If the match fails, the request expires or the MyDigital ID service is unavailable, return to the sign-in page and use your OneID user ID and password. You may also try another MyDigital ID account when that option is provided.
TEXT,
                ],
                'mfa' => [
                    'question' => 'Why am I asked for additional verification?',
                    'answer' => <<<'TEXT'
OneID may require Multi-Factor Authentication (MFA) after the password has been verified. Depending on the security policy for your account or user category, verification may use an OTP sent to your registered email address or a code from Microsoft Authenticator.

Enter the code only on an official OneID page, and never share an OTP, Authenticator code or setup key with anyone. If your Authenticator factor is lost or unavailable, follow the recovery option shown or contact OneID support.
TEXT,
                ],
                'application-access' => [
                    'question' => 'What is the difference between Full SSO and Non-SSO?',
                    'answer' => <<<'TEXT'
A Full SSO application accepts authentication from OneID. When you select “Sign in”, OneID issues access for that application and you normally do not need to enter your OneID password again.

A Non-SSO application or direct link does not yet use the full SSO flow. OneID opens the application address, but that application may request separate credentials. Only applications assigned to your account are displayed; final access remains subject to the destination application's policy.
TEXT,
                ],
                'security' => [
                    'question' => 'How does OneID protect my account?',
                    'answer' => <<<'TEXT'
OneID uses several layers of control, including secure connections, protected password storage, SSO tokens, session timeouts, OTP or MFA when required, and audit records for security actions. Sensitive administrator access also requires additional verification.

Security also depends on the user. Confirm that you are on an official UPNM domain, use a unique password, never share verification codes, and sign out on public computers. If you receive an unfamiliar request or notification, stop and contact OneID support.
TEXT,
                ],
                'multiple-devices' => [
                    'question' => 'Can I sign in on multiple devices?',
                    'answer' => <<<'TEXT'
This depends on the multiple-session policy configured by the administrator. If multiple sessions are allowed, you can have more than one active session. If the policy is disabled, a new sign-in may make an older token or session invalid, requiring the older device to sign in again.

Every session is also subject to an inactivity timeout and an overall maximum lifetime. End sessions on devices you no longer use, and never leave your account open on a public or shared device.
TEXT,
                ],
                'single-application-sign-out' => [
                    'question' => 'Does one sign-out close every application?',
                    'answer' => <<<'TEXT'
Not necessarily. Signing out from a destination application normally ends only that application's session; it does not automatically close the OneID portal or other applications.

Signing out from OneID ends the current OneID session and token. However, a destination application that created its own session may remain open until you sign out there or its session expires. On a public computer, sign out from sensitive applications, sign out from OneID, and close every browser window.
TEXT,
                ],
                'forgot-password' => [
                    'question' => 'What should I do if I forget my password?',
                    'answer' => <<<'TEXT'
Select Forgot Password on the sign-in page, enter your user ID, and follow the instructions shown. If the account is eligible, a six-digit OTP is sent to the registered email address. Enter a valid, unexpired OTP, then set and confirm the new password.

Never share the OTP with anyone. After the password is reset successfully, older OneID tokens are revoked and you must sign in again. If the email does not arrive, your email details are no longer correct, or the account cannot be verified, contact OneID support using the contact information on the sign-in page.
TEXT,
                ],
                'password-requirements' => [
                    'question' => 'What are the password requirements?',
                    'answer' => <<<'TEXT'
A password must contain at least 12 characters, including at least one uppercase letter, one lowercase letter, one number and one symbol. It must not be common or easily predictable, and it must not contain your user ID.

Use a unique password that is not used for another system. OneID also prevents reuse of the current password and recent passwords in the account history. After a password change or reset, other sessions may be ended and you may be required to sign in again.
TEXT,
                ],
            ],
        ];
    }
}
