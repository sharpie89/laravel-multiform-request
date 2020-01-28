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

        if ($this->isFirstMultiFormRequest()) {
            $this->validateMultiFormRequests();
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
    private function validateMultiFormRequests(): void
    {
        $factory = $this->container->make(ValidationFactory::class);

        /** @var Validator $validator */
        $validator = $factory->make(
            $this->validationData(),
            $this->getMultiFormRequestRules(),
            $this->getMultiFormRequestMessages(),
            $this->getMultiFormRequestAttributes()
        );

        if ($validator->fails()) {
            $this->failedValidation($validator);
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
                /* @var \Modules\Project\Http\Requests\MultiFormRequest $multiFormRequest */
                $multiFormRequest = new $class;

                $this->addMultiFormRequest($class);
                $this->addMultiFormRequestRules($multiFormRequest->rules());
                $this->addMultiFormRequestAttributes($multiFormRequest->attributes());
                $this->addMultiFormRequestMessages($multiFormRequest->messages());
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

    private function isFirstMultiFormRequest(): bool
    {
        return head($this->getMultiFormRequests()) === static::class;
    }

    private function addMultiFormRequest(string $class): void
    {
        $this->multiFormRequests[] = $class;
    }

    private function addMultiFormRequestRules(array $rules): void
    {
        $this->multiFormRequestRules = array_merge($this->multiFormRequestRules, $rules);
    }

    private function addMultiFormRequestAttributes(array $attributes): void
    {
        $this->multiFormRequestAttributes = array_merge($this->multiFormRequestAttributes, $attributes);
    }

    private function addMultiFormRequestMessages(array $messages): void
    {
        $this->multiFormRequestMessages = array_merge($this->multiFormRequestMessages, $messages);
    }

    public function getMultiFormRequests(): array
    {
        return $this->multiFormRequests;
    }

    public function getMultiFormRequestRules(): array
    {
        return $this->multiFormRequestRules;
    }

    public function getMultiFormRequestAttributes(): array
    {
        return $this->multiFormRequestAttributes;
    }

    public function getMultiFormRequestMessages(): array
    {
        return $this->multiFormRequestMessages;
    }
}
