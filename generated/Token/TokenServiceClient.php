<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Token;

/**
 */
class TokenServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * @param \Token\CreateTokenRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Token\CreateTokenResponse>
     */
    public function CreateToken(\Token\CreateTokenRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/token.TokenService/CreateToken',
        $argument,
        ['\Token\CreateTokenResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Token\ReadTokenRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Token\ReadTokenResponse>
     */
    public function ReadToken(\Token\ReadTokenRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/token.TokenService/ReadToken',
        $argument,
        ['\Token\ReadTokenResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Token\UpdateTokenRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Token\UpdateTokenResponse>
     */
    public function UpdateToken(\Token\UpdateTokenRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/token.TokenService/UpdateToken',
        $argument,
        ['\Token\UpdateTokenResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Token\DeleteTokenRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Token\DeleteTokenResponse>
     */
    public function DeleteToken(\Token\DeleteTokenRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/token.TokenService/DeleteToken',
        $argument,
        ['\Token\DeleteTokenResponse', 'decode'],
        $metadata, $options);
    }

}
