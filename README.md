# [LEGACY] IntlBundle

**abandoned!**

Use [intl component](https://github.com/symfony/intl) instead.

```yaml
# config.yml

krg_intl:
    locales: [fr, en]

twig:
    form_themes:
        - 'KRGIntlBundle:Form:bootstrap_4_layout.html.twig'
        
stof_doctrine_extensions:
    orm:
        default:
           translatable:    true

doctrine:
    orm:
        resolve_target_entities:
            KRG\IntlBundle\Entity\TranslationInterface: AppBundle\Entity\Intl\Translation
```

```yaml
# easyadmin.yml

imports:
    - { resource: '@KRGCmsBundle/Resources/config/easyadmin_intl.yml' }
    
easy_admin:
    design:
        form_theme:
            - 'horizontal'
            - 'KRGIntlBundle:Form:bootstrap_3_horizontal_layout.html.twig'
```

```yaml
# routing.yml

krg_intl:
    resource: "@KRGIntlBundle/Controller/"
    type:     annotation
```

```php
<?php

namespace AppBundle\Entity\Intl;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table("intl_translation")
 */
class Translation extends \KRG\IntlBundle\Entity\Translation
{
}
```

```sh
bin/console assets:install --symlink
```
