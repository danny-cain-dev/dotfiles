<?php

namespace DannyCain\Net\Base\Exceptions;

class NetException extends \Exception {
    protected $fatal = false;

    public function __construct( $message = "", $isFatal = false) {
        parent::__construct( $message );
        $this->fatal = $isFatal;
    }

    /**
     * @return bool
     */
    public function isFatal(): bool {
        return $this->fatal;
    }

    /**
     * @param bool $fatal
     */
    public function setFatal( bool $fatal ): void {
        $this->fatal = $fatal;
    }
}
