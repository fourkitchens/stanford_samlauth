# [Stanford SamlAuth](https://github.com/SU-SWS/stanford_samlauth)
##### 1.x

Changelog: [Changelog.md](CHANGELOG.md)

Description
---

This is an enhancement module for [SamlAuth module](https://www.drupal.org/project/samlauth).

Installation
---

Place the Saml cert and key on the server in a protected location. They should be kept secret.
Add the following to the settings.php file indicating the path to the cert files:
```php
$config['samlauth.authentication']['sp_x509_certificate'] = 'file:/path/to/cert.crt';
$config['samlauth.authentication']['sp_private_key'] = 'file:/path/to/cert.key';

// If using signing to authenticate the service.
$config['samlauth.authentication']['idp_certs'][0] = 'file:/path/to/signing/cert.crt';

// Optional Workgroup API cert and key.
$config['stanford_samlauth.settings']['role_mapping']['workgroup_api']['cert'] = '/path/to/workgroup/cert.crt';
$config['stanford_samlauth.settings']['role_mapping']['workgroup_api']['key'] = '/path/to/workgroup/cert.key';
```

Configuration
---

Follow instructions for SamlAuth module.


Troubleshooting
---

If you are experiencing issues with this try posting an issue on the [GitHub issues page](https://github.com/SU-SWS/stanford_samlauth/issues).

Contribution / Collaboration
---

You are welcome to contribute functionality, bug fixes, or documentation to this module. If you would like to suggest a fix or new functionality you may add a new issue to the GitHub issue queue or you may fork this repository and submit a pull request. For more help please see [GitHub's article on fork, branch, and pull requests](https://help.github.com/articles/using-pull-requests)
