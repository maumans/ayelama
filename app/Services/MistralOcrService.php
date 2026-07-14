<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Extraction structurée depuis une photo/scan (pièce d'identité ou fiche
 * papier) via l'API Mistral OCR — utilisée par le formulaire public de
 * demande externe pour pré-remplir les champs avant relecture par le client.
 *
 * N'échoue jamais vers l'appelant : en cas de clé absente, d'erreur réseau ou
 * de réponse inattendue, retourne un tableau vide et le client bascule
 * simplement en saisie manuelle.
 */
class MistralOcrService
{
    /**
     * @param  array<int, array{id: string, label: string}>  $champs
     * @return array<string, mixed>
     */
    public function extraire(string $cheminImage, array $champs): array
    {
        $apiKey = config('services.mistral.key');
        if (!$apiKey || !file_exists($cheminImage)) {
            return [];
        }

        try {
            $encoded = base64_encode(file_get_contents($cheminImage));
            $mime    = mime_content_type($cheminImage) ?: 'image/jpeg';

            $schema = [
                'type'       => 'object',
                'properties' => collect($champs)->mapWithKeys(fn ($c) => [$c['id'] => ['type' => 'string']])->all(),
            ];

            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post(rtrim(config('services.mistral.base_url'), '/') . '/v1/ocr', [
                    'model'    => 'mistral-ocr-latest',
                    'document' => [
                        'type'         => 'document_url',
                        'document_url' => "data:{$mime};base64,{$encoded}",
                    ],
                    'document_annotation_format' => [
                        'type'        => 'json_schema',
                        'json_schema' => [
                            'name'   => 'fiche_client',
                            'schema' => $schema,
                        ],
                    ],
                ]);

            if (!$response->successful()) {
                Log::warning('MistralOcrService: réponse non réussie', ['status' => $response->status()]);
                return [];
            }

            $annotation = $response->json('document_annotation');
            if (is_string($annotation)) {
                $annotation = json_decode($annotation, true);
            }

            return is_array($annotation) ? $annotation : [];
        } catch (\Throwable $e) {
            Log::warning('MistralOcrService: échec extraction OCR', ['message' => $e->getMessage()]);
            return [];
        }
    }
}
