<?php

namespace Sharpie89\MultiFormRequest\Http\Requests;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Validation\Validator;

trait MergesValidators
{
    protected function mergeValidators(array $validators): ValidatorContract
    {
        $data = [];
        $rules = [];
        $customMessages = [];
        $customAttributes = [];

        /** @var Validator $validator */
        foreach ($validators as $validator) {
            $data = array_merge($data, $validator->getData());
            $rules = array_merge($rules, $validator->getRules());
            $customMessages = array_merge($customMessages, $validator->customMessages);
            $customAttributes = array_merge($customAttributes, $validator->customAttributes);
        }

        $factory = $this->container->make(ValidationFactory::class);

        return $factory->make($data, $rules, $customMessages, $customAttributes);
    }
}
