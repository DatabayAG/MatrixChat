# UIHook Plugin - MatrixChatClient

## Requirements

| Component | Min                                              | Max                                              | Link                      |
|-----------|--------------------------------------------------|--------------------------------------------------|---------------------------|
| PHP       | ![](https://img.shields.io/badge/7.3-blue.svg)   | ![](https://img.shields.io/badge/7.4-blue.svg)   | [PHP](https://php.net)    |
| ILIAS     | ![](https://img.shields.io/badge/7.x-orange.svg) | ![](https://img.shields.io/badge/7.x-orange.svg) | [ILIAS](https://ilias.de) |

---
## Table of contents

- [UIHook Plugin - MatrixChatClient](#uihook-plugin---matrixchatclient)
    * [Requirements](#requirements)
    * [Installation](#installation)
    * [Usage](#usage)
    * [Matrix-Setup](docs/Matrix-Setup.md)
    * [Roadmap](docs/ROADMAP.md)

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

## Usage

ToDo