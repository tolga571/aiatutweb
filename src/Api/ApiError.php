<?php
namespace App\Src\Api;

/** A handled API failure: becomes {"ok": false, "error": {code, message, ...extra}} with $status. */
class ApiError extends \RuntimeException
{
    public string $errorCode;
    public int $status;
    public array $extra;

    public function __construct(string $code, string $message, int $status = 400, array $extra = [])
    {
        parent::__construct($message);
        $this->errorCode = $code;
        $this->status = $status;
        $this->extra = $extra;
    }
}
