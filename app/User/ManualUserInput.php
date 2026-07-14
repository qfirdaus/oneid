<?php

namespace OneId\App\User;

use InvalidArgumentException;
use OneId\App\Sync\SyncDataTransformer;

final class ManualUserInput
{
    /** @param array<string, string> $data */
    private function __construct(
        public readonly string $userId,
        public readonly int $categoryId,
        public readonly string $name,
        public readonly array $data
    ) {
    }

    /** @param array<string, mixed> $post */
    public static function fromPost(array $post): self
    {
        $userId = self::clean($post['add_new_manual_user_id'] ?? '');
        $name = self::clean($post['add_new_manual_user_name'] ?? '');
        $categoryRaw = self::clean($post['add_new_manual_user_category'] ?? '');

        if ($userId === '' || self::length($userId) > 20
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9._@-]*$/', $userId) !== 1) {
            throw new InvalidArgumentException(
                'User ID mesti 1 hingga 20 aksara dan hanya menggunakan huruf, nombor, titik, garis bawah, @ atau sempang.'
            );
        }

        if ($name === '' || self::length($name) > 100
            || preg_match('/[\x00-\x1F\x7F<>]/', $name) === 1) {
            throw new InvalidArgumentException('Nama pengguna diperlukan dan tidak boleh melebihi 100 aksara.');
        }

        if ($categoryRaw === '' || filter_var($categoryRaw, FILTER_VALIDATE_INT) === false || (int) $categoryRaw < 0) {
            throw new InvalidArgumentException('Kategori pengguna tidak sah.');
        }

        $data = [];
        foreach ([2, 3, 5, 6, 7, 8, 9, 10, 11, 12] as $index) {
            $field = 'data' . $index;
            $value = self::clean($post['add_new_user_' . $field] ?? '');
            if (self::length($value) > 100
                || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F<>]/', $value) === 1) {
                throw new InvalidArgumentException(sprintf('Data %d tidak sah atau melebihi 100 aksara.', $index));
            }
            $data[$field] = $value;
        }

        if ($data['data5'] === '' || filter_var($data['data5'], FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Alamat email yang sah diperlukan untuk proses OTP dan penetapan password.');
        }

        $data['data4'] = $userId;

        return new self($userId, (int) $categoryRaw, $name, $data);
    }

    public function changeHash(): string
    {
        return SyncDataTransformer::computeHash(
            $this->name,
            $this->data['data2'],
            $this->data['data3'],
            $this->data['data4'],
            $this->data['data5'],
            $this->data['data6'],
            $this->data['data7'],
            $this->data['data8'],
            $this->data['data9'],
            $this->data['data10'],
            $this->data['data11'],
            $this->data['data12'],
            'manual:' . $this->categoryId
        );
    }

    private static function clean(mixed $value): string
    {
        return trim(is_scalar($value) ? (string) $value : '');
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
