<?php

use PHPUnit\Framework\TestCase;

class ResponseContractTest extends TestCase
{
    public function testSuccessPayloadUsesEnvelopeAndEmptyObjectWhenDataIsOmitted(): void
    {
        $payload = Response::successPayload();

        $this->assertJsonStringEqualsJsonString(
            '{"success":true,"data":{}}',
            json_encode($payload)
        );
    }

    public function testSuccessPayloadPreservesEmptyArraysAndNullNestedData(): void
    {
        $payload = Response::successPayload([
            'items' => [],
            'selected' => null,
        ]);

        $this->assertTrue($payload['success']);
        $this->assertSame([], $payload['data']['items']);
        $this->assertNull($payload['data']['selected']);
    }

    public function testErrorPayloadUsesEnvelopeAndEmptyDetailsObjectByDefault(): void
    {
        $payload = Response::errorPayload('Resource not found.', 'NOT_FOUND');

        $this->assertJsonStringEqualsJsonString(
            '{"success":false,"error":{"code":"NOT_FOUND","message":"Resource not found.","details":{}}}',
            json_encode($payload)
        );
    }

    public function testValidationErrorPayloadKeepsFieldErrorsInsideDetails(): void
    {
        $payload = Response::validationErrorPayload([
            ['field' => 'email', 'message' => 'Invalid email.'],
            ['field' => 'password', 'message' => 'Senha obrigatoria.'],
        ]);

        $this->assertFalse($payload['success']);
        $this->assertSame('VALIDATION_ERROR', $payload['error']['code']);
        $this->assertSame('email', $payload['error']['details'][0]['field']);
        $this->assertSame('Invalid email.', $payload['error']['details'][0]['message']);
        $this->assertSame('password', $payload['error']['details'][1]['field']);
    }

    public function testValidationErrorPayloadAcceptsFieldMessageMap(): void
    {
        $payload = Response::validationErrorPayload([
            'state' => 'Invalid state.',
        ]);

        $this->assertSame([
            [
                'field' => 'state',
                'message' => 'Invalid state.',
            ],
        ], $payload['error']['details']);
    }
}
