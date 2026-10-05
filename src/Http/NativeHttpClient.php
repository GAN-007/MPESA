<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Http;

use Safaricom\Mpesa\Exception\PaymentException;

final class NativeHttpClient implements HttpClient
{
    public function __construct(
        private readonly int $timeoutSeconds = 30,
        private readonly int $connectTimeoutSeconds = 10,
    ) {}

    public function request(string $method, string $url, array $headers = [], ?string $body = null): HttpResponse
    {
        return function_exists('curl_init')
            ? $this->requestWithCurl($method, $url, $headers, $body)
            : $this->requestWithStreams($method, $url, $headers, $body);
    }

    private function requestWithCurl(string $method, string $url, array $headers, ?string $body): HttpResponse
    {
        $curl = curl_init($url);
        if ($curl === false) {
            throw PaymentException::transport('Unable to initialize cURL.');
        }

        $responseHeaders = [];
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = is_int($name) ? (string) $value : $name . ': ' . $value;
        }

        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeoutSeconds,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$responseHeaders): int {
                $length = strlen($line);
                $line = trim($line);
                if ($line !== '' && str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $responseHeaders[strtolower(trim($name))][] = trim($value);
                }
                return $length;
            },
        ]);
        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($curl);
        if ($responseBody === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw PaymentException::transport($error);
        }
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        return new HttpResponse($status, $responseHeaders, (string) $responseBody);
    }

    private function requestWithStreams(string $method, string $url, array $headers, ?string $body): HttpResponse
    {
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = is_int($name) ? (string) $value : $name . ': ' . $value;
        }

        $context = stream_context_create([
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", $headerLines),
                'content' => $body ?? '',
                'timeout' => $this->timeoutSeconds,
                'ignore_errors' => true,
                'protocol_version' => 1.1,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ]);

        $responseBody = @file_get_contents($url, false, $context);
        $rawHeaders = $http_response_header ?? [];
        if ($responseBody === false && $rawHeaders === []) {
            throw PaymentException::transport('HTTP request failed using PHP streams.');
        }

        $status = 0;
        $parsedHeaders = [];
        foreach ($rawHeaders as $index => $line) {
            if ($index === 0 && preg_match('/HTTP\/\S+\s+(\d{3})/', $line, $matches)) {
                $status = (int) $matches[1];
                continue;
            }
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $parsedHeaders[strtolower(trim($name))][] = trim($value);
            }
        }

        return new HttpResponse($status, $parsedHeaders, $responseBody === false ? '' : $responseBody);
    }
}
