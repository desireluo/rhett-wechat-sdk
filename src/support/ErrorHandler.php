<?php
declare(strict_types=1);

namespace RhettWechatSdk\support;

use GuzzleHttp\Exception\RequestException;
use InvalidArgumentException;
use UnexpectedValueException;

class ErrorHandler
{
    public static function handleException(\Throwable $e): array
    {
        if ($e instanceof RequestException) {
            $r = $e->getResponse();
            return ['status' => false, 'message' => $r->getReasonPhrase(), 'errors' => ['status_code' => $r->getStatusCode(), 'body' => $r->getBody()->getContents()]];
        } elseif ($e instanceof UnexpectedValueException || $e instanceof InvalidArgumentException) {
            return ['status' => false, 'message' => $e->getMessage(), 'errors' => []];
        } else {
            return ['status' => false, 'message' => $e->getMessage(), 'errors' => []];
        }
    }
}