# Onaxis ExPlatform Extra #

## Instalation ##

__Using composer:__

`composer require onaxis/ezplatform-extra`

__Routing:__

Add the following in your routing.yml file:

```
onaxis_ezplatform_extra:
    resource: "@OnaxisEzPlatformExtraBundle/Resources/config/routing.yml"
```

This routing file enable the route `onaxis_ezplatform_extra.user.selfedit` (/user/selfedit)

__Role:__

You will have to add the `user/selfedit` policy to your users role.

__Information__

The field `user_account.enabled` will never be editable for the user to avoid user disabling himself.

## Extra configuration ##

You can customize the user self edit form creating multiple version of this form.

For example, you could need to create 2 pages:
- A profile edition page
- A password edition page

Example of a customized forms:

```
onaxis_ez_platform_extra:
    user_self_edit_form_filters:
        profile:
            # type is optional: default value is 'include/exclude'
            # other posible value is 'exclude/include'
            type: 'include/exclude'
            exclude: ['user_account']
        account:
            include: ['user_account']
        password:
            include: ['user_account']
            exclude: ['user_account.username', 'user_account.email']
```

The previous configuration enable the following URLs:

- /user/selfedit/profile
- /user/selfedit/account
- /user/selfedit/password

This is how to generate these routes:

```
<a href="{{ path('onaxis_ezplatform_extra.user.selfedit', {'filter': 'profile'}) }}">Home</a>
<a href="{{ path('onaxis_ezplatform_extra.user.selfedit', {'filter': 'account'}) }}">Contact</a>
<a href="{{ path('onaxis_ezplatform_extra.user.selfedit', {'filter': 'password'}) }}">Privacy</a>
```

To know available fields, use the following command:

`php bin/console ezplatform-extra:user-self-edit:form-fields`

By default, listed fields correspond to the admin user content type (user) (UserID: 14)

__Output example:__

```
User Content ID:    14
Content Type ID:    4
Content Identifier: user
----------------------------

 - first_name
 - last_name
 - user_account
 - user_account.username
 - user_account.password
 - user_account.password.first
 - user_account.password.second
 - user_account.email
 - user_account.enabled
 - signature
 - image
 - image.remove
 - image.file
 - image.alternativeText
```

To be aware of fields of another content type, just pass a user content id:
(A user with this content type must exist in your backend)

`php bin/console ezplatform-extra:user-self-edit:form-fields 80`

__Output example:__

```
User Content ID:    73
Content Type ID:    13
Content Identifier: spaceobs_user
----------------------------

 - firstname
 - lastname
 - user_account
 - user_account.username
 - user_account.password
 - user_account.password.first
 - user_account.password.second
 - user_account.email
 - user_account.enabled
```