<?php

namespace App\Services\WebSocket;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

class MessageValidator
{
    private Validator $validator;

    private string $schemasPath;

    public function __construct(?string $schemasPath = null)
    {
        $this->schemasPath = $schemasPath ?? base_path('../chief/contract/schemas');
        $this->validator = new Validator;
        $this->validator->setMaxErrors(10);
    }

    /**
     * Validate a message against the envelope schema and type-specific schema.
     *
     * @param  array<string, mixed>  $message
     * @return array{valid: bool, errors: array<string, mixed>}
     */
    public function validate(array $message): array
    {
        $envelopeResult = $this->validateEnvelope($message);

        if (! $envelopeResult['valid']) {
            return $envelopeResult;
        }

        $type = $message['type'];
        $typeSchemaResult = $this->validateTypeSchema($message, $type);

        if (! $typeSchemaResult['valid']) {
            return $typeSchemaResult;
        }

        if (isset($message['payload'])) {
            $payloadResult = $this->validatePayloadTypes($message['payload'], $type);

            if (! $payloadResult['valid']) {
                return $payloadResult;
            }
        }

        return ['valid' => true, 'errors' => []];
    }

    /**
     * Validate the message envelope format.
     *
     * @param  array<string, mixed>  $message
     * @return array{valid: bool, errors: array<string, mixed>}
     */
    private function validateEnvelope(array $message): array
    {
        return $this->validateAgainstSchema($message, 'envelope.json');
    }

    /**
     * Validate the full message against a type-specific schema.
     *
     * @param  array<string, mixed>  $message
     * @return array{valid: bool, errors: array<string, mixed>}
     */
    private function validateTypeSchema(array $message, string $type): array
    {
        $schemaFile = $this->resolveTypeSchemaFile($type);

        if (! $schemaFile) {
            return ['valid' => true, 'errors' => []];
        }

        return $this->validateAgainstSchema($message, $schemaFile);
    }

    /**
     * Validate embedded types within the payload (project, prd, run).
     *
     * @param  array<string, mixed>  $payload
     * @return array{valid: bool, errors: array<string, mixed>}
     */
    private function validatePayloadTypes(array $payload, string $type): array
    {
        $typeChecks = [
            'prd' => 'types/prd.json',
            'project' => 'types/project.json',
            'run' => 'types/run.json',
        ];

        foreach ($typeChecks as $field => $schemaFile) {
            if (isset($payload[$field]) && is_array($payload[$field])) {
                $result = $this->validateAgainstSchema($payload[$field], $schemaFile);

                if (! $result['valid']) {
                    return [
                        'valid' => false,
                        'errors' => array_map(
                            fn (string $error) => "payload.{$field}: {$error}",
                            $result['errors'],
                        ),
                    ];
                }
            }
        }

        if (isset($payload['projects']) && is_array($payload['projects'])) {
            foreach ($payload['projects'] as $index => $project) {
                if (is_array($project)) {
                    $result = $this->validateAgainstSchema($project, 'types/project.json');

                    if (! $result['valid']) {
                        return [
                            'valid' => false,
                            'errors' => array_map(
                                fn (string $error) => "payload.projects[{$index}]: {$error}",
                                $result['errors'],
                            ),
                        ];
                    }
                }
            }
        }

        if (isset($payload['prds']) && is_array($payload['prds'])) {
            foreach ($payload['prds'] as $index => $prd) {
                if (is_array($prd)) {
                    $result = $this->validateAgainstSchema($prd, 'types/prd.json');

                    if (! $result['valid']) {
                        return [
                            'valid' => false,
                            'errors' => array_map(
                                fn (string $error) => "payload.prds[{$index}]: {$error}",
                                $result['errors'],
                            ),
                        ];
                    }
                }
            }
        }

        if (isset($payload['runs']) && is_array($payload['runs'])) {
            foreach ($payload['runs'] as $index => $run) {
                if (is_array($run)) {
                    $result = $this->validateAgainstSchema($run, 'types/run.json');

                    if (! $result['valid']) {
                        return [
                            'valid' => false,
                            'errors' => array_map(
                                fn (string $error) => "payload.runs[{$index}]: {$error}",
                                $result['errors'],
                            ),
                        ];
                    }
                }
            }
        }

        return ['valid' => true, 'errors' => []];
    }

    /**
     * Resolve the schema file path for a given message type.
     */
    private function resolveTypeSchemaFile(string $type): ?string
    {
        if (str_starts_with($type, 'state.') || str_starts_with($type, 'state-')) {
            return null;
        }

        $prefixMap = [
            'ack' => 'control/ack.json',
            'error' => 'control/error.json',
            'welcome' => 'control/welcome.json',
        ];

        if (isset($prefixMap[$type])) {
            return $prefixMap[$type];
        }

        $typeSlug = $type;
        $candidates = [
            "state/{$typeSlug}.json",
            "control/{$typeSlug}.json",
            "cmd/{$typeSlug}.json",
        ];

        foreach ($candidates as $candidate) {
            $fullPath = $this->schemasPath.'/'.$candidate;

            if (file_exists($fullPath)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Validate data against a specific JSON schema file.
     *
     * @param  array<string, mixed>  $data
     * @return array{valid: bool, errors: array<string, mixed>}
     */
    private function validateAgainstSchema(array $data, string $schemaFile): array
    {
        $schemaPath = $this->schemasPath.'/'.$schemaFile;

        if (! file_exists($schemaPath)) {
            return ['valid' => true, 'errors' => []];
        }

        $schemaContent = file_get_contents($schemaPath);
        $schema = json_decode($schemaContent);

        if ($schema === null) {
            return ['valid' => true, 'errors' => []];
        }

        // Opis requires absolute URIs for $id — prefix relative $id with a base URI
        if (isset($schema->{'$id'}) && ! str_starts_with($schema->{'$id'}, 'http')) {
            $schema->{'$id'} = 'https://chief-uplink.local/schemas/'.$schema->{'$id'};
        }

        $dataObject = json_decode(json_encode($data));

        $result = $this->validator->validate($dataObject, $schema);

        if ($result->isValid()) {
            return ['valid' => true, 'errors' => []];
        }

        $formatter = new ErrorFormatter;
        $formattedErrors = $formatter->format($result->error());
        $flatErrors = [];

        foreach ($formattedErrors as $path => $messages) {
            foreach ($messages as $msg) {
                $flatErrors[] = "{$path}: {$msg}";
            }
        }

        return ['valid' => false, 'errors' => $flatErrors];
    }
}
