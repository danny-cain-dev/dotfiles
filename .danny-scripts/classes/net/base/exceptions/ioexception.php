<?php

namespace DannyCain\Net\Base\Exceptions;

class IOException extends \Exception {
    protected $fatal = false;

    public function __construct( string $message = "") {
        parent::__construct( $message );
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
