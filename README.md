# Laravel multi form request

composer require sharpie89/laravel-multiform-request

## Usage

Just extend your current FormRequest with MultiFormRequest and chain the requests together in your controller method.

## Advantage

If you don't like the idea of cluttering your controller to extract parameters from a single form request when having 2 or more entities or models, this may suit you. The $request->validated() method only returns the parameters that belong to that MultiFormRequest class and the thrown ValidationException returns a MessageBag that contains all fields.
