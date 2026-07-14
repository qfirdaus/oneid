<?php

namespace OneId\App\User;

use PDOException;
use Throwable;

final class ManualUserCreator
{
    public function __construct(private readonly object $operation)
    {
    }

    /** @return array{status:int,msg:string,code:string,correlation_id:string} */
    public function create(ManualUserInput $input, string $actor, string $ipAddress): array
    {
        $correlationId = bin2hex(random_bytes(8));

        if (!method_exists($this->operation, 'supportsUserProvenance')
            || !$this->operation->supportsUserProvenance()) {
            return $this->result(
                0,
                'Manual Add User belum tersedia sehingga migration provenance S1 dipasang.',
                'PROVENANCE_MIGRATION_REQUIRED',
                $correlationId
            );
        }

        $transactionStarted = false;
        try {
            $this->operation->beginTransaction();
            $transactionStarted = true;

            if (!empty($this->operation->get_specific_user_info($input->userId))) {
                $this->operation->rollback();
                return $this->result(0, 'User ID telah digunakan.', 'USER_ID_EXISTS', $correlationId);
            }

            if (!$this->operation->isActiveUserCategory($input->categoryId)) {
                $this->operation->rollback();
                return $this->result(0, 'Kategori pengguna tidak aktif atau tidak sah.', 'INVALID_CATEGORY', $correlationId);
            }

            $initialPasswordHash = oneid_password_hash(bin2hex(random_bytes(32)));
            $this->operation->action_add_new_user(
                $input->userId,
                $input->categoryId,
                $initialPasswordHash,
                $input->name,
                $input->data['data2'],
                $input->data['data3'],
                $input->data['data4'],
                $input->data['data5'],
                $input->data['data6'],
                $input->data['data7'],
                $input->data['data8'],
                $input->data['data9'],
                $input->data['data10'],
                $input->data['data11'],
                $input->data['data12'],
                $input->changeHash(),
                'manual',
                1
            );
            $this->operation->syslog_record(23, $actor . ' -> ' . $input->userId, $ipAddress);
            $this->operation->commit();

            return $this->result(1, 'User berjaya ditambah. Pengguna perlu menetapkan password melalui OTP.', 'CREATED', $correlationId);
        } catch (PDOException $exception) {
            if ($transactionStarted) {
                $this->safeRollback();
            }
            if ($this->isDuplicateKey($exception)) {
                return $this->result(0, 'User ID telah digunakan.', 'USER_ID_EXISTS', $correlationId);
            }
            $this->logFailure($exception, $correlationId);
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $this->safeRollback();
            }
            $this->logFailure($exception, $correlationId);
        }

        return $this->result(
            0,
            'User tidak dapat ditambah. ID rujukan: ' . $correlationId,
            'CREATE_FAILED',
            $correlationId
        );
    }

    private function safeRollback(): void
    {
        try {
            $this->operation->rollback();
        } catch (Throwable $rollbackError) {
            error_log('Manual user rollback failed: ' . get_class($rollbackError));
        }
    }

    private function isDuplicateKey(PDOException $exception): bool
    {
        $driverCode = $exception->errorInfo[1] ?? null;
        return (string) $exception->getCode() === '23000' || (int) $driverCode === 1062;
    }

    private function logFailure(Throwable $exception, string $correlationId): void
    {
        error_log(sprintf(
            'Manual user creation failed correlation_id=%s exception=%s',
            $correlationId,
            get_class($exception)
        ));
    }

    /** @return array{status:int,msg:string,code:string,correlation_id:string} */
    private function result(int $status, string $message, string $code, string $correlationId): array
    {
        return [
            'status' => $status,
            'msg' => $message,
            'code' => $code,
            'correlation_id' => $correlationId,
        ];
    }
}
