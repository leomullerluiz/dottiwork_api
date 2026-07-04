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
        $payload = Response::errorPayload('Recurso nao encontrado.', 'NOT_FOUND');

        $this->assertJsonStringEqualsJsonString(
            '{"success":false,"error":{"code":"NOT_FOUND","message":"Recurso nao encontrado.","details":{}}}',
            json_encode($payload)
        );
    }

    public function testValidationErrorPayloadKeepsFieldErrorsInsideDetails(): void
    {
        $payload = Response::validationErrorPayload([
            ['field' => 'email', 'message' => 'Email invalido.'],
            ['field' => 'password', 'message' => 'Senha obrigatoria.'],
        ]);

        $this->assertFalse($payload['success']);
        $this->assertSame('VALIDATION_ERROR', $payload['error']['code']);
        $this->assertSame('email', $payload['error']['details'][0]['field']);
        $this->assertSame('Email invalido.', $payload['error']['details'][0]['message']);
        $this->assertSame('password', $payload['error']['details'][1]['field']);
    }

    public function testValidationErrorPayloadAcceptsFieldMessageMap(): void
    {
        $payload = Response::validationErrorPayload([
            'state' => 'Estado invalido.',
        ]);

        $this->assertSame([
            [
                'field' => 'state',
                'message' => 'Estado invalido.',
            ],
        ], $payload['error']['details']);
    }
}
