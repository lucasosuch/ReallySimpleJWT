<?php

declare(strict_types=1);

namespace ReallySimpleJWT;

use ReallySimpleJWT\Helper\Validator;
use ReallySimpleJWT\Interfaces\Encode;
use ReallySimpleJWT\Jwt;
use ReallySimpleJWT\Interfaces\Secret;
use ReallySimpleJWT\Exception\BuildException;

/**
 * A class to build a JSON Web Token, returns the token as an instance of
 * ReallySimpleJWT\Jwt.
 *
 * Class contains helper methods that allow you to easily set JWT claims
 * defined in the JWT RFC. Eg setIssuer() will set the iss claim in the
 * JWT payload.
 *
 * For more information on JSON Web Tokens please refer to the RFC. This
 * library attempts to comply with the JWT RFC as closely as possible.
 * https://tools.ietf.org/html/rfc7519
 *
 * @author Rob Waller <rdwaller1984@googlemail.com>
 */
class Build
{

    /**
     * Defines the type of JWT to be created, usually just JWT.
     */
    private string $type;

    /**
     * Holds the JWT header claims
     *
     * @var mixed[]
     */
    private array $header = [];

    /**
     * Holds the JWT payload claims.
     *
     * @var mixed[]
     */
    private array $payload = [];

    /**
     * The secret string for encoding the JWT signature.
     */
    private string $secret;

    /**
     * Token claim validator.
     */
    private Validator $validate;

    /**
     * Signature secret validator.
     */
    private Secret $secretValidator;

    /**
     * Token Encoder which complies with the encoder interface.
     */
    private Encode $encode;

    public function __construct(string $type, Validator $validate, Secret $secretValidator, Encode $encode)
    {
        $this->type = $type;

        $this->validate = $validate;

        $this->secretValidator =  $secretValidator;

        $this->encode = $encode;
    }

    /**
     * Define the content type header claim for the JWT. This defines
     * structural information about the token. For instance if it is a
     * nested token.
     */
    public function setContentType(string $contentType): Build
    {
        $this->header['cty'] = $contentType;

        return $this;
    }

    /**
     * Add custom claims to the JWT header
     *
     * @param mixed $value
     */
    public function setHeaderClaim(string $key, $value): Build
    {
        $this->header[$key] = $value;

        return $this;
    }

    /**
     * Get the contents of the JWT header. This is an associative array of
     * the defined header claims. The JWT algorithm and typ are added
     * by default.
     *
     * @return mixed[]
     */
    public function getHeader(): array
    {
        return array_merge(
            $this->header,
            ['alg' => $this->encode->getAlgorithm(), 'typ' => $this->type]
        );
    }

    /**
     * Set the JWT secret for encrypting the JWT signature. The secret must
     * comply with the validation rules defined in the
     * ReallySimpleJWT\Validate class.
     * 
     * @throws BuildException
     */
    public function setSecret(string $secret): Build
    {
        if (!$this->secretValidator->validate($secret)) {
            throw new BuildException('Invalid secret.', 9);
        }

        $this->secret = $secret;

        return $this;
    }

    /**
     * Set the issuer JWT payload claim. This defines who issued the token.
     * Can be a string or URI.
     */
    public function setIssuer(string $issuer): Build
    {
        $this->payload['iss'] = $issuer;

        return $this;
    }

    /**
     * Set the subject JWT payload claim. This defines who the JWT is for.
     * Eg an application user or admin.
     */
    public function setSubject(string $subject): Build
    {
        $this->payload['sub'] = $subject;

        return $this;
    }

    /**
     * Set the audience JWT payload claim. This defines a list of 'principals'
     * who will process the JWT. Eg a website or websites who will validate
     * users who use this token. This claim can either be a single string or an
     * array of strings.
     *
     * @param mixed $audience
     * @throws BuildException
     */
    public function setAudience($audience): Build
    {
        if (is_string($audience) || is_array($audience)) {
            $this->payload['aud'] = $audience;

            return $this;
        }

        throw new BuildException('Invalid Audience claim.', 10);
    }

    /**
     * Set the expiration JWT payload claim. This sets the time at which the
     * JWT should expire and no longer be accepted.
     *
     * @throws BuildException
     */
    public function setExpiration(int $timestamp): Build
    {
        if (!$this->validate->expiration($timestamp)) {
            throw new BuildException('Expiration claim has expired.', 4);
        }

        $this->payload['exp'] = $timestamp;

        return $this;
    }

    /**
     * Set the not before JWT payload claim. This sets the time after which the
     * JWT can be accepted.
     */
    public function setNotBefore(int $notBefore): Build
    {
        $this->payload['nbf'] = $notBefore;

        return $this;
    }

    /**
     * Set the issued at JWT payload claim. This sets the time at which the
     * JWT was issued / created.
     */
    public function setIssuedAt(int $issuedAt): Build
    {
        $this->payload['iat'] = $issuedAt;

        return $this;
    }

    /**
     * Set the JSON token identifier JWT payload claim. This defines a unique
     * identifier for the token.
     */
    public function setJwtId(string $jwtId): Build
    {
        $this->payload['jti'] = $jwtId;

        return $this;
    }

    /**
     * Set a custom payload claim on the JWT. The RFC calls these private
     * claims. Eg you may wish to set a user_id or a username in the
     * JWT payload.
     * 
     * @param mixed $value
     */
    public function setPayloadClaim(string $key, $value): Build
    {
        $this->payload[$key] = $value;

        return $this;
    }

    /**
     * Get the JWT payload. This will return an array of registered claims and
     * private claims which make up the JWT payload.
     *
     * @return mixed[]
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * Build the token, this is the last method which should be called after
     * all the header and payload claims have been set. It will encode the
     * header and payload, and generate the JWT signature. It will then
     * concatenate each part with dots into a single string.
     *
     * This JWT string along with the secret are then used to generate a new
     * instance of the JWT class which is returned.
     */
    public function build(): Jwt
    {
        return new Jwt(
            $this->encode->encode($this->getHeader()) . "." .
            $this->encode->encode($this->getPayload()) . "." .
            $this->getSignature(),
            $this->secret
        );
    }

    /**
     * If you wish to use the same build instance to generate two or more
     * tokens you can use this reset method to unset the pre-defined header,
     * payload and secret properties.
     */
    public function reset(): Build
    {
        $this->payload = [];
        $this->header = [];
        $this->secret = '';

        return $this;
    }

    /**
     * Generate and return the JWT signature this is made up of the header,
     * payload and secret.
     * 
     * @throws Exception\BuildException
     */
    private function getSignature(): string
    {
        if ($this->secretValidator->validate($this->secret)) {
            return $this->encode->signature(
                $this->getHeader(),
                $this->getPayload(),
                $this->secret
            );
        }

        throw new BuildException('Invalid secret.', 9);
    }
}
