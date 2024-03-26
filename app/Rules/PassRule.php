<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class PassRule implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return preg_match('/[a-z]/', $value) &&
        preg_match('/[A-Z]/', $value) && 
        preg_match('/\d/', $value) &&
        strlen($value) >= 3;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Пароль должен состоять минимум из 3 символов, из которых как минимум одна строчная, одна прописная и одна цифра';
    }
}
