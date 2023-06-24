<?php

namespace App;

class Validator
{
    public function validate(array $user)
    {
        $errors = [];

        if ($user['nickname'] === '') {
            $errors['nickname'] = 'Can\'t be blank'; 
        }

        if ($user['email'] === '') {
            $errors['email'] = 'Can\'t be blank'; 
        }

        return $errors;
    }
}
