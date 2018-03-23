<?php

namespace src\Api;

use src\Api\Exceptions\WrongDataException;

class ContactInfo extends RbkDataObject
{

    /**
     * Адрес электронной почты
     *
     * @var string | null
     */
    protected $email;

    /**
     * Номер мобильного телефона с международным префиксом согласно E.164
     *
     * @var string | null
     */
    protected $phoneNumber;

    /**
     * @param string $email
     *
     * @return ContactInfo
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @param string $phoneNumber
     *
     * @return ContactInfo
     *
     * @throws WrongDataException
     */
    public function setPhone($phoneNumber)
    {
        if (!preg_match('/^\+\d{4,15}$/', $phoneNumber)) {
            throw new WrongDataException('Неверное значение поля `phoneNumber`');
        }

        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $properties = array();

        foreach ($this as $property => $value) {
            if (!empty($value)) {
                $properties[$property] = $value;
            }
        }

        return $properties;
    }

}
