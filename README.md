# Fungo Metrics backend
this repo is for maintenance code for :
- Backend webapp
- API for mobile app

## packages
For this project used the packages and versions:
#### Production:
- [laravel/framework (9.19)](https://packagist.org/packages/laravel/laravel)
- [league/flysystem-aws-s3-v3 (3.0)](https://packagist.org/packages/league/flysystem-aws-s3-v3)
- [tightenco/ziggy (1.4)](https://packagist.org/packages/tightenco/ziggy)
- [laravel/sanctum (3.0)](https://packagist.org/packages/laravel/sanctum)
- [twilio/sdk (6.41)](https://packagist.org/packages/twilio/sdk)

#### Development:
- [opcodesio/log-viewer (1.2)](https://packagist.org/packages/opcodesio/log-viewer)
- [pestphp/pest (1.21)](https://packagist.org/packages/pestphp/pest)
- [pestphp/pest-plugin-laravel (1.2)](https://packagist.org/packages/pestphp/pest-plugin-laravel)
- [laravel/pint (1.0)](https://packagist.org/packages/laravel/pint)
- [nunomaduro/larastan (1.0)](https://packagist.org/packages/nunomaduro/larastan)


### Codes for total bases live ab
The order of the codes is to allow the organization of the statistics in alphabetical and numerical order.
| Code | Final value |
|------|-------------|
|1|1B|
|2|2B|
|3|3B|
|7|K|
|4|BB|
|6|HBP|
|5|HR|
|8|Out/E|

# Api docs 
To access the documentation and API test you can access the route
`request-docs`


# Images from app
For the correct functioning of the images, you must create a bucket in AWS, fill in the environment variables for correct communication, the variables to consider are the following:
```php
AWS_ACCESS_KEY_ID=""
AWS_SECRET_ACCESS_KEY=""
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET="name of bucket"
AWS_USE_PATH_STYLE_ENDPOINT=false
```

