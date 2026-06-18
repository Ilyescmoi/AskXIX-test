<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * ApiKeyFilter — protège la route de génération par une clé.
 *
 * La clé attendue vient de l'environnement (REPORT_API_KEY). Fournie par
 * ?key=… ou par l'en-tête X-Api-Key. Si aucune clé n'est configurée, l'accès
 * est libre (pratique en développement local).
 */
class ApiKeyFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $expected = (string) (env('REPORT_API_KEY') ?: '');
        if ($expected === '') {
            return; // pas de clé configurée -> accès libre (dev)
        }
        $provided = (string) ($request->getGet('key') ?? '');
        if ($provided === '') {
            $provided = (string) $request->getHeaderLine('X-Api-Key');
        }
        if (!hash_equals($expected, $provided)) {
            return Services::response()->setStatusCode(401)
                ->setJSON(['error' => 'Clé API invalide ou absente.']);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
