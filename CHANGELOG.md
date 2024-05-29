# Stanford SamlAuth

1.0.9
--------------------------------------------------------------------------------
_Release Date: 2024-05-29_

- Store workgroup api responses during the login to avoid unneeded requests.

1.0.8
--------------------------------------------------------------------------------
_Release Date: 2024-05-28_

- Adjusted Dependency versions

1.0.7
--------------------------------------------------------------------------------
_Release Date: 2024-04-23_

- Fixed update hook number.

1.0.6
--------------------------------------------------------------------------------
_Release Date: 2024-04-22_

- Add configurable workgroup api timeout and increase it (#12)


1.0.4
--------------------------------------------------------------------------------
_Release Date: 2024-03-29_

- Fix saml login block to only use the path, not query params.

1.0.3
--------------------------------------------------------------------------------
_Release Date: 2023-09-28_

- Handle basic sunets without a mail attribute.
- Fixed empty role population during install.

1.0.2
--------------------------------------------------------------------------------
_Release Date: 2023-08-08_

- Update unit tests for D10.

1.0.1
--------------------------------------------------------------------------------
_Release Date: 2023-08-07_

- Use Saml attributes for role mapping and authoriztion
- Use a controller to handle redirecting legacy simplesamlphp routes.

1.0.0
--------------------------------------------------------------------------------
_Release Date: 2023-08-05_

- Initial release
