# cakephp-utils


## Installation

You can install the plugin cackephp-utils into your CakePHP application using [composer](http://getcomposer.org) and executing the
following lines in the root of your application.

```
composer require whiterabbit/cackephp-utils 
```

## Configuration

You will need to add the following line to your application's bootstrap.php file:

```php
Plugin::load('WRUtils');
```

For the S3File Helper, in your AppController add

```php
public function initialize() {
    $this->helpers[] = 'Utils.Search';
}
```


    

## version

0.1.3
Added S3FileHelper helper

0.1.2
Added folder management for S3

0.1.1
Initial release

License
-------

The MIT License (MIT)

Copyright (c) 2016 WhiteRabbit by Dino Fratelli

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
