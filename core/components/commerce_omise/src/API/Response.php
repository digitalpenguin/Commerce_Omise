<?php

namespace DigitalPenguin\Commerce_Omise\API;

use Psr\Http\Message\ResponseInterface;

class Response {
    /**
     * @var bool
     */
    private $success;
    /**
     * @var array
     */
    private $data;

    private $errors = [];
    /**
     * @var int
     */
    private $statusCode;

    public function __construct(bool $success, int $statusCode, array $data = [])
    {
        $this->success = $success;
        $this->data = $data;
        $this->statusCode = $statusCode;
    }

    public function addError(string $code, string $message): void
    {
        $this->errors[] = ['code' => $code, 'message' => $message];
    }

    public static function from(ResponseInterface $response): self
    {
        $body = $response->getBody()->getContents();
        $statusCode = $response->getStatusCode();
        $success = strpos($statusCode, '2') === 0;
        $data = json_decode($body, true);

        if (!is_array($data)) {
            $data = [];
        }

        $inst = new static(
            $success,
            $statusCode,
            $data
        );

        if (!$success) {
            $errCode = $data['error_code'];
            $errMessages = array_key_exists('error_message', $data) ? [$data['error_message']] : $data['error_messages'];
            foreach ($errMessages as $msg) {
                $inst->addError($errCode, $msg);
            }
        }

        return $inst;
    }

    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}