<?php

namespace Sharpie89\MultiFormRequest\Http\Requests;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use ReflectionException;
use ReflectionMethod;

abstract class MultiFormRequest extends ExtendableFormRequest
{
    use MergesValidators;

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
        $this->setInitialData();
        $this->replaceDataByRules();
        $this->setMultiFormRequests();
        $this->prepareForValidation();

        if (!$this->passesAuthorization()) {
            $this->failedAuthorization();
        }

        if ($this->isFirstMultiFormRequest()) {
            $this->validateMultiFormRequests();
        }

        $instance = $this->getValidatorInstance();

        if ($instance->passes()) {
            $this->passedValidation();
        }
    }

    /**
     * @throws BindingResolutionException
     * @throws ValidationException
     */
    private function validateMultiFormRequests(): void
    {
        foreach ($this->getMultiFormRequests() as $class) {
            $multiFormRequest = $this->extend($class);

            if ($class !== static::class) {
                $multiFormRequest->prepareForValidation();
            }

            $validators[] = $multiFormRequest->getValidatorInstance();
        }

        $validator = $this->mergeValidators($validators);

        if ($validator->fails()) {
            $this->failedValidation($validator);
        }
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