# UIHook Plugin - MatrixChatClient

## Requirements

| Component | Min                                              | Max                                              | Link                      |
|-----------|--------------------------------------------------|--------------------------------------------------|---------------------------|
| PHP       | ![](https://img.shields.io/badge/7.3-blue.svg)   | ![](https://img.shields.io/badge/7.4-blue.svg)   | [PHP](https://php.net)    |
| ILIAS     | ![](https://img.shields.io/badge/7.x-orange.svg) | ![](https://img.shields.io/badge/7.x-orange.svg) | [ILIAS](https://ilias.de) |

---
## Table of contents

<!-- TOC -->
* [UIHook Plugin - MatrixChatClient](#uihook-plugin---matrixchatclient)
  * [Requirements](#requirements)
  * [Table of contents](#table-of-contents)
  * [Installation](#installation)
  * [Inner Workings](#inner-workings)
    * [Adding User to Course](#adding-user-to-course)
    * [Removing User from Course](#removing-user-from-course)
    * [Opening the Chat Window](#opening-the-chat-window)
  * [Usage](#usage)
    * [User Configuration](#user-configuration)
      * [Authentication Methods](#authentication-methods)
  * [Matrix-Setup](docs/Matrix-Setup.md)
  * [Roadmap](docs/ROADMAP.md)
<!-- TOC -->

---

## Installation

1. Clone this repository to **/var/www/ilias7/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/MatrixChatClient/MatrixChatClient**
2. Install the Composer dependencies
   ```bash
   cd /var/www/ilias7/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/MatrixChatClient/MatrixChatClient
   composer install --no-dev
   ```
   Developers **MUST** omit the `--no-dev` argument.


3. Login to ILIAS with an administrator account (e.g. root)
4. Select **Plugins** in **Extending ILIAS** inside the **Administration** main menu.
5. Search for the **MatrixChatClient** plugin in the list of plugin and choose **Install** from the **Actions** drop-down.
6. Choose **Activate** from the **Actions** dropdown.


## Inner Workings

### Adding User to Course

- When an ILIAS user is added to a course  
  The plugin checks if the user has already (successfully) configured chat authentication (See [User Configuration](#user-configuration).
  - If the user has configured authentication:  
    - The user is immediately invited to the chat room of the course.
  - If the user has not yet configured authentication:  
    - The user is added to a waiting list.   
    - Once the user completes the configuration. The user is invited to the chat room of the course.

### Removing User from Course

- When an ILIAS user is removed from a course  
  The user will be removed from the chat room of the course as well.

### Opening the Chat Window

- If a user hasn't configured authentication or the authentication with the Matrix server failed.  
  The user is redirected to the [User Configuration](#user-configuration) page.

## Usage

### User Configuration

1. Click the Profile image in the top right corner of the ILIAS UI.
2. Select **Settings** from the dropdown.
3. Select the **Chat Settings** Tab.

- The page shows the status of the authentication above the form.
- In the **General settings** sub tab. Select your authentication method.

#### Authentication Methods
- Use External Account
  - Uses the **External Account** field of your ILIAS account to authenticate.
- Authentication With Existing Account or Creation of New Account Using Naming Scheme
  - Two sub tabs will be shown.
    - Authenticate With Existing Account
      - Enter the username and password of your Matrix account.
    - Create Account Using Naming Scheme
      - Enter a username, a predefined suffix will be added after your entered name to avoid conflicts.  
        (This combination is the actual username, not what was entered in the field)
      - Enter the password for your new Matrix-User.
      - Enter the password again to confirm.
    - In both cases if authentication was successful. A message will be shown with your username and Matrix-User-ID.

ToDo