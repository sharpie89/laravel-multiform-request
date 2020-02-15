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

abstract class MultiFormRequest extends FormRequest
{
    /**
     * @var array
     */
    private $multiFormRequests = [];
    /**
     * @var array
     */
    private $multiFormRequestValidatorData = [];
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

        $this->replaceMultiFormRequestParameters();

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
            $multiFormRequest = $this->initializeMultiFormRequest($class);

            if ($class !== static::class) {
                $multiFormRequest->prepareForValidation();
            }

            $this->mergeValidatorData($multiFormRequest);
        }

        $this->validateWithValidatorData();
    }

    private function validateWithValidatorData(): void
    {
        $factory = $this->container->make(ValidationFactory::class);

        /** @var Validator $validator */
        $validator = $factory->make(
            $this->multiFormRequestValidatorData,
            $this->multiFormRequestRules,
            $this->multiFormRequestMessages,
            $this->multiFormRequestAttributes
        );

        if ($validator->fails()) {
            $this->failedValidation($validator);
        }
    }

    private function initializeMultiFormRequest(string $class): self
    {
        /** @var self $multiFormRequest */
        $multiFormRequest = new $class;
        $multiFormRequest->setContainer(app());
        $multiFormRequest->setRedirector(app(Redirector::class));
        $multiFormRequest->initialize(
            $this->query->all(),
            array_intersect_key(
                $this->request->all(),
                $multiFormRequest->rules()
            ),
            $this->attributes->all(),
            $this->cookies->all(),
            $this->files->all(),
            $this->server->all(),
            $this->getContent()
        );

        return $multiFormRequest;
    }

    private function mergeValidatorData(self $multiFormRequest): void
    {
        $this->multiFormRequestValidatorData = array_merge(
            $this->multiFormRequestValidatorData,
            $multiFormRequest->all()
        );
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