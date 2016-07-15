<?php

namespace Indoctrinated\Entity;

use StdClass;

abstract class Validator
{
    protected $_errors = [];
    protected $_fields;
    protected $_vars;
    protected $_isValidated = false;

    public function __construct(
        array $vars,
        array $fields
    )
    {
        $this->_fields = $fields;
        $this->_vars = $vars;
    }

    public function error(
        string $message,
        string $field = null
    )
    {
        $error = new StdClass();
        $error->message = $message;

        if ($field) {
            $error->field = $field;
        }

        $this->_errors[] = $error;

        return $this;
    }

    public function validate()
    {
        $this->_isValidated = true;

        return $this;
    }

    public function isValid()
    {
        if (!$this->_isValidated) {
            $this->validate();
        }

        return empty($this->_errors);
    }

    public function getErrors()
    {
        return $this->_errors;
    }

    /**
     * @return array
     */
    public function getVars()
    {
        return $this->_vars;
    }

    /**
     * @return array
     */
    public function getFields()
    {
        return $this->_fields;
    }
}
