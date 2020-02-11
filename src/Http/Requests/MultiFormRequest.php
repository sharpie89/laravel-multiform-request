<?php

namespace Sharpie89\MultiFormRequest\Http\Requests;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use ReflectionException;
use ReflectionMethod;

abstract class MultiFormRequest extends FormRequest
{
    /**
     * @var array
     */
    private $multiFormRequests = [];

    /**
     * @throws ReflectionException
     * @throws ValidationException
     * @throws AuthorizationException
     * @throws BindingResolutionException
     */
    public function validateResolved(): void
    {
        $this->setMultiFormRequests();
        $this->prepareForValidation();

        if (!$this->passesAuthorization()) {
            $this->failedAuthorization();
        }

        if ($this->isFirstMultiFormRequest()) {
            $this->validateMultiFormRequests();
        }

        $this->replaceMultiFormRequestParameters();

        $instance = $this->getValidatorInstance();

        if (!$instance->fails()) {
            $this->passedValidation();
        }
    }

    /**
     * @throws BindingResolutionException
     * @throws ValidationException
     */
    private function validateMultiFormRequests(
        array $validationData = [],
        array $rules = [],
        array $messages = [],
        array $attributes = []
    ): void {
        $factory = $this->container->make(ValidationFactory::class);

        foreach($this->getMultiFormRequests() as $class) {
            /** @var self $multiFormRequest */
            $multiFormRequest = $class::createFromBase($this);

            if ($class !== static::class) {
                $multiFormRequest->prepareForValidation();
            }

            $multiFormRequest->replaceMultiFormRequestParameters();

            $validationData = array_merge($validationData, $multiFormRequest->all());
            $rules = array_merge($rules, $multiFormRequest->rules());
            $messages = array_merge($messages, $multiFormRequest->attributes());
            $attributes = array_merge($attributes, $multiFormRequest->messages());
        }

        /** @var Validator $validator */
        $validator = $factory->make($validationData, $rules, $messages, $attributes);

        if ($validator->fails()) {
            $this->failedValidation($validator);
        }
    }

    private function replaceMultiFormRequestParameters(): void
    {
        $this->replace(array_intersect_key($this->all(), $this->rules()));
    }

    /**
     * @throws ReflectionException
     */
    private function setMultiFormRequests(): void
    {
        $controllerAction = Route::getCurrentRoute()
            ->getActionName();

        $controllerMethod = explode('@', $controllerAction);
        $reflectionMethod = new ReflectionMethod(...$controllerMethod);

        foreach ($reflectionMethod->getParameters() as $parameter) {
            $class = $parameter->getClass()->getName();

            if (is_subclass_of($class, self::class)) {
                $this->multiFormRequests[] = $class;
            }
        }
    }

    private function isFirstMultiFormRequest(): bool
    {
        return head($this->getMultiFormRequests()) === static::class;
    }

    protected function getMultiFormRequests(): array
    {
        return $this->multiFormRequests;
    }
}
