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

class MultiFormRequest extends FormRequest
{
    /**
     * @var array
     */
    private $multiFormRequests = [];
    /**
     * @var array
     */
    private $multiFormRequestRules = [];
    /**
     * @var array
     */
    private $multiFormRequestMessages = [];
    /**
     * @var array
     */
    private $multiFormRequestAttributes = [];

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

        if ($this->isFirst()) {
            $this->setMultiFormRequestDetails();
            $this->validateAll();
        }

        $instance = $this->getValidatorInstance();

        if (!$instance->fails()) {
            $this->passedValidation();
        }
    }

    /**
     * @throws BindingResolutionException
     * @throws ValidationException
     */
    private function validateAll(): void
    {
        $factory = $this->container->make(ValidationFactory::class);

        /** @var Validator $validator */
        $validator = $factory->make(
            $this->validationData(),
            $this->multiFormRequestRules,
            $this->multiFormRequestMessages,
            $this->multiFormRequestAttributes
        );

        if ($validator->fails()) {
            $this->failedValidation($validator);
        }
    }

    private function setMultiFormRequestDetails(): array
    {
        foreach ($this->multiFormRequests as $multiFormRequestClass) {
            /* @var self $multiFormRequest */
            $multiFormRequest = new $multiFormRequestClass;

            $this->multiFormRequestRules = array_merge(
                $this->multiFormRequestRules,
                $multiFormRequest->rules()
            );
            $this->multiFormRequestMessages = array_merge(
                $this->multiFormRequestMessages,
                $multiFormRequest->messages()
            );
            $this->multiFormRequestAttributes = array_merge(
                $this->multiFormRequestAttributes,
                $multiFormRequest->attributes()
            );
        }
    }

    /**
     * @throws ReflectionException
     */
    private function setMultiFormRequests(): void
    {
        foreach ($this->getCurrentControllerParameters() as $parameter) {
            $class = $parameter->getClass()->getName();

            if (is_subclass_of($class, self::class)) {
                $this->multiFormRequests[] = $class;
            }
        }
    }

    /**
     * @return array
     * @throws ReflectionException
     */
    private function getCurrentControllerParameters(): array
    {
        $controllerAction = Route::getCurrentRoute()
            ->getActionName();

        $controllerMethod = explode('@', $controllerAction);
        $reflectionMethod = new ReflectionMethod(...$controllerMethod);

        return $reflectionMethod->getParameters();
    }

    private function isFirst(): bool
    {
        return head($this->multiFormRequests) === static::class;
    }
}
