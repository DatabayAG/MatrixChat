# UIHook Plugin - MatrixChat

## Requirements

| Component | Min                                              | Max                                              | Link                      |
|-----------|--------------------------------------------------|--------------------------------------------------|---------------------------|
| PHP       | ![](https://img.shields.io/badge/7.4-blue.svg)   | ![](https://img.shields.io/badge/8.0-blue.svg)   | [PHP](https://php.net)    |
| ILIAS     | ![](https://img.shields.io/badge/8.x-orange.svg) | ![](https://img.shields.io/badge/8.x-orange.svg) | [ILIAS](https://ilias.de) |

---
## Table of contents

<!-- TOC -->
* [UIHook Plugin - MatrixChat](#uihook-plugin---matrixchat)
  * [Requirements](#requirements)
  * [Table of contents](#table-of-contents)
  * [Notes](#notes)
  * [Installation](#installation)
    * [Adding User to Course/Group](#adding-user-to-coursegroup)
    * [Removing User from Course/Group](#removing-user-from-coursegroup)
    * [Setting up a Homeserver](#setting-up-a-homeserver)
  * [Usage](#usage)
    * [User Configuration](#user-configuration)
      * [Authentication Methods](#authentication-methods)
<!-- TOC -->

---

## Notes

1. Matrix has rate limiting for API-Requests. This may lead to the users used by the plugin   
   (an admin user & a normal user who creates the room) to be blocked from further API-Requests.
   - The Plugin-Configuration has two checkboxes that will remove this rate limiting.
2. Another homeserver may restrict the amount of invitations that can be send by a user.
   This may lead to users of a different homeserver (for example `@user:matrix2.myDomain.de`) to not be invitable for a period of time.
3. The plugin uses **inviting** only for rooms.   
   The only user immediately added to the room is the user who created the room.
4. Users are invited to the **room** of the course/group as well as a **space** that groups all rooms.
   - Changing the name configured in the Plugin-Configuration will lead to a new **Space** being created.  
     - Rooms will not be transferred to the new space.
     - The old space will not be deleted.
5. Users may choose to not accept the invitation to the space. This is optional,   
   the room will then not be grouped in clients.

## Installation

1. Clone this repository to **Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/MatrixChat**
2. Install the Composer dependencies
   ```bash
   cd Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/MatrixChat
   composer install --no-dev
   ```
   Developers **MUST** omit the `--no-dev` argument.

3. Login to ILIAS with an administrator account (e.g. root)
4. Select **Plugins** in **Extending ILIAS** inside the **Administration** main menu.
5. Search for the **MatrixChat** plugin in the list of plugin and choose **Install** from the **Actions** drop-down.
6. Choose **Activate** from the **Actions** dropdown.
7. Add the following configuration to your ``homeserver.yaml`` file to guarantee the plugin works as intended  
   (rc values may be adjusted based on the setup, these are just example values that should work in most scenarios):
   ```yaml
    rc_login:
        address:
            per_second: 10
            burst_count: 10
        account:
            per_second: 10
            burst_count: 10
        failed_attempts:
            per_second: 10
            burst_count: 10
    serve_server_wellknown: true
    ```

### Adding User to Course/Group

- When an ILIAS user is added to a course/group  
  the plugin checks if the user has already (successfully) configured a Matrix-Account (See [User Configuration](#user-configuration).
  - If the user has configured a Matrix-Account:  
    - The user is immediately invited to the chatroom of the course/group.
  - If the user has not yet configured a Matrix-Account:  
    - The user is added to a waiting list.   
    - Once the user completes the configuration. The user is invited to the chatroom of the course/group.
      - Alternatively:
        - A User with **write** permission on the course/group can manually   
        invite members into the chatroom using the **Members** sub-tab of the **Chat** Tab.
        - A Cronjob periodically goes through all queued invitations   
        and invites users if they can be invited.
        
### Removing User from Course/Group

- When an ILIAS user is removed from a course/group.  
  The user will be removed from the chatroom as well.

### Setting up a Homeserver

See [For instructions on how to setup a synapse homeserver](https://matrix-org.github.io/synapse/latest/setup/installation.html#installation-instructions)

## Usage

### User Configuration

1. Click the Profile image in the top right corner of the ILIAS UI.
2. Select **Settings** from the dropdown.
3. Select the **Matrix Chat Setting** Tab.

- This tab shows the current Matrix-Account status.
- If not yet configured. The tab will show a form with different options for configuring a Matrix-Account.

#### Authentication Methods

- Use account on the connected homeserver
  - The username field is fixed and cannot be changed. This is set based on a scheme defined in the plugin configuration.
  - If a user with this username already exists. The form can immediately be saved.
  - If a user with this username does not exist. You will be asked to enter a password. A new user will be created on the configured homeserver.
- Use another Matrix account
  - The shown input field mus contain the entire Matrix-User-ID of the user. The user may be located on a different matrix-server.
  - Example: ``@user:matrix2.myDomain.de``
  - The button **Check account on Matrix-Server** can be used to check if a profile exists on the homeserver of the account **(matrix2.myDomain.de)**.
    - The other Matrix-Server may not allow looking up a users profile through another homeserver. The **Check account on Matrix-Account** may fail.   
      You can still decide to save this matrix-account if you are sure it exists and can be invited.