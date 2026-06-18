<?php

namespace App\Controllers;

/**
 * Docs — documentation développeur de l'API, servie en HTML à GET /docs.
 *
 * Page publique (pas de clé requise) : elle ne fait que décrire l'API. L'URL de
 * base et la clé d'exemple sont déduites de la requête et de l'environnement,
 * pour que les exemples soient directement copiables.
 */
class Docs extends BaseController
{
    public function index(): string
    {
        // On reflète l'hôte réellement utilisé pour consulter la doc (en-tête Host),
        // pas app.baseURL : les exemples restent ainsi copiables tels quels.
        $scheme = $this->request->getUri()->getScheme() ?: 'http';
        $host   = $this->request->getHeaderLine('Host') ?: $this->request->getUri()->getAuthority();
        $base   = $scheme . '://' . $host;

        // En dev, on affiche la vraie clé pour faciliter les tests ; sinon un placeholder.
        $apiKey = ENVIRONMENT === 'development'
            ? (string) (env('REPORT_API_KEY') ?: 'demo-key')
            : 'VOTRE_CLE';

        return view('docs', [
            'base'   => $base,
            'apiKey' => $apiKey,
        ]);
    }
}
