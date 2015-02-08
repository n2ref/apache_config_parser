Apache config parser
====================

Parse apache config

## Example
```php

require_once('Apache_Config_Parser.php');

$server_apache_config = '/etc/apache2/apache2.conf';

$apache_config_parser = new Apache_Config_Parser($server_apache_config);
$apache_hosts         = $apache_config_parser->getApacheHosts();

echo'<pre>';print_r($apache_hosts);echo'</pre>';
```

Result

```html
Array (
    [host1.com] => Array (
        [is_active] => 1
        [document_root] => /home/username1/www/host1.com
        [aliases] => Array (
            0 => Array (
                [name] => www.host1.com
                [is_active] => 1
            )
            1 => Array (
                [name] => h1.com
                [is_active] => 1
            )
        )
    )

    [host2.com] => Array (
        [is_active] => 1
        [document_root] => /home/username2/www/host2.com
        [aliases] => Array (
        )
    )

    [host3.com] => Array (
        [is_active] => 1
        [document_root] => /home/username2/www/host2.com
        [aliases] => Array (
        )
    )
    
    ...
    
)        
```
