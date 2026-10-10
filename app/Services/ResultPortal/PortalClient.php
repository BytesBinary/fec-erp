<?php

namespace App\Services\ResultPortal;

use App\Exceptions\Domain\ResultPortalException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The only class that talks HTTP to the portal.
 */
class PortalClient
{
    public function sessionsHtml(): string
    {
        return $this->guard(fn (): string => $this->http()->get('/result.php')->throw()->body());
    }

    public function examOptionsHtml(int $programId): string
    {
        return $this->guard(fn (): string => $this->http()->get('/ajax/get_program_by_exam.php', ['program_id' => $programId, 'pedata' => 99])->throw()->body());
    }

    public function lookupHtml(string $registrationNumber, int $programId, int $sessionId, int $examId): string
    {
        return $this->guard(fn (): string => $this->http()->asForm()->post('/ajax/get_program_by_exam.php', [
            'reg_no' => $registrationNumber,
            'pro_id' => $programId,
            'sess_id' => $sessionId,
            'exam_id' => $examId,
            'gdata' => 99,
        ])->throw()->body());
    }

    protected function http(): PendingRequest
    {
        return Http::baseUrl((string) config('result_portal.base_url'))
            ->withUserAgent((string) config('result_portal.user_agent'))
            ->timeout((int) config('result_portal.timeout_seconds'));
    }

    /**
     * @param  callable(): string  $request
     */
    protected function guard(callable $request): string
    {
        try {
            return $request();
        } catch (ConnectionException $exception) {
            throw ResultPortalException::unavailable($exception->getMessage());
        } catch (\Illuminate\Http\Client\RequestException $exception) {
            throw ResultPortalException::unavailable('HTTP '.$exception->response->status());
        }
    }
}
