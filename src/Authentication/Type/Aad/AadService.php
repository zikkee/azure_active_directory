<?php

namespace Concrete\Package\AzureActiveDirectory\Authentication\Type\Aad;

use OAuth\Common\Http\Exception\TokenResponseException;
use OAuth\Common\Http\Uri\UriInterface;
use OAuth\OAuth2\Token\StdOAuth2Token;
use OAuth\OAuth2\Token\TokenInterface;
use Concrete\Core\Support\Facade\Application as App;
use Concrete\Core\Authentication\Type\ExternalConcrete\ExternalConcreteService;

class AadService extends ExternalConcreteService
{

    /** @var string Scope for forcing OIDC */
    const SCOPE_OPENID= 'openid';

    /** @var string Scope for system info */
    const SCOPE_SYSTEM = 'system';

    /** @var string Scope for site tree info */
    const SCOPE_SITE = 'site';

    /** @var string Scope for authenticated user */
    const SCOPE_ACCOUNT = 'account';

    /** @var string Authorization path */
    const PATH_AUTHORIZE = '/oauth2/v2.0/authorize';

    /** @var string Token path */
    const PATH_TOKEN = '/oauth2/v2.0/token';

    const SCOPE_USER = 'user.read';

    /**
     * Parses the access token response and returns a TokenInterface.
     *
     *
     * @param string $responseBody
     * @return TokenInterface
     * @throws TokenResponseException
     */
    protected function parseAccessTokenResponse($responseBody)
    {
        $body = json_decode($responseBody, true);

        if (array_key_exists('hint', $body) && array_key_exists('error', $body) && isset($body['error'])) {
            throw new TokenResponseException($body['hint']);
        }

        $token = new StdOAuth2Token();
        if (array_key_exists('access_token', $body)) {
            $token->setAccessToken($body['access_token']);
        }
        if (array_key_exists('refresh_token', $body)) {
            $token->setRefreshToken($body['refresh_token']);
        }
        if (array_key_exists('expires_in', $body)) {
            $token->setLifetime($body['expires_in']);
        }

        // Store the id_token as an "extra param"
        if (array_key_exists('id_token', $body)) {
            $token->setExtraParams(['id_token' => $body['id_token']]);
        }

        return $token;
    }

    /**
     * Returns the authorization API endpoint.
     *
     * @return UriInterface
     */
    public function getAuthorizationEndpoint()
    {
        $uri = $this->getBaseApiUri();
        $config = App::make('config');
        $data = $config->get('auth.aad', '');
        $uri->setPath('/' . $data['directoryid'] . self::PATH_AUTHORIZE);

        return $uri;
    }

    /**
     * Returns the access token API endpoint.
     *
     * @return UriInterface
     */
    public function getAccessTokenEndpoint()
    {
        $uri = $this->getBaseApiUri();
        $config = App::make('config');
        $data = $config->get('auth.aad', '');
        $uri->setPath('/' . $data['directoryid'] . self::PATH_TOKEN);

        return $uri;
    }

    /**
     * Return a copy of our base api uri
     *
     * @return \OAuth\Common\Http\Uri\UriInterface
     */
    public function getBaseApiUri()
    {
        return clone $this->baseApiUri;
    }

    /**
     * Declare that we use the bearer header field
     * We want our headers to be:
     *     Authorization: Bearer SOMETOKEN
     *
     * If we didn't declare this everything would break because they'd be
     *     Authorization: Bearer OAuth SOMETOKEN
     *
     * @return int
     */
    protected function getAuthorizationMethod()
    {
        return self::AUTHORIZATION_METHOD_HEADER_BEARER;
    }
}
