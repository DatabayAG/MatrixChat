# Matrix backend setup

## Install Matrix "Synapse" homeserver

```bash
sudo apt install -y lsb-release wget apt-transport-https
sudo wget -O /usr/share/keyrings/matrix-org-archive-keyring.gpg https://packages.matrix.org/debian/matrix-org-archive-keyring.gpg
echo "deb [signed-by=/usr/share/keyrings/matrix-org-archive-keyring.gpg] https://packages.matrix.org/debian/ $(lsb_release -cs) main" |
    sudo tee /etc/apt/sources.list.d/matrix-org.list
sudo apt update
sudo apt install matrix-synapse-py3
```

## Setup database (Postgres)

1. verify ``libpq5`` is installed
    ```bash
    sudo apt install libpq5
    ```
2. install postgres
    ```bash
    sudo apt install postgresql postgresql-contrib
    ```
3. Login as the postgres user
    ```bash
    sudo su postgres
    ```
4. Create a database user for synapse
    ```bash
    createuser --pwprompt matrix_user
    ```
5. Login into the postgres db server:
    ```bash
    psql
    ```
6. Create a database table for synapse
    ```postgresql
    CREATE DATABASE matrix_synapse
    ENCODING 'UTF8'
    LC_COLLATE='C'
    LC_CTYPE='C'
    template=template0
    OWNER matrix_user;
    ```

## Configure Synapse homeserver

1. **[OPTIONAL]** Change the server_name
   ```bash
   sudo nano /etc/matrix-synapse/conf.d/server_name.yaml
   ```

   Change the name of the server to something different like ``matrix-server``

2. Configure the homeserver
   ```bash
   nano /etc/matrix-synapse/homeserver.yaml
   ```
   - Change ``database`` to
      ```yaml
      database:
        name: psycopg2
        args:
          user: matrix_user
          password: dbpassword
          database: matrix_synapse
          host: localhost
          cp_min: 5
          cp_max: 10
      ```
      Replace ``dbpassword`` with your chosen password
   - Add the ``registration_shared_secret`` entry
      ```yaml
      registration_shared_secret: "sharedsecret"
      ```
      Replace ``sharedsecret`` with your randomly chosen string (<span style="color: red; font-weight: bold">Keep it secret</span>)
3. Restart matrix server
```bash
sudo service matrix-synapse restart
```
4. Register a new admin user
```bash
register_new_matrix_user -u <Username> -p <Password> -a -c /etc/matrix-synapse/homeserver.yaml
```
- Note that the ``<Username>`` should be **lowercase**.
- Replace ``<Username>`` with your desired username
- Replace ``<Password>`` with your desired password
- ``-a´`` means Admin user.
- ``-c`` is the path to the homeserver config file.

## Enabling LDAP login (Without SSL!)

Add the following section to the file ``/etc/matrix-synapse/homeserver.yaml``

```yaml
modules:
 - module: "ldap_auth_provider.LdapAuthProviderModule"
   config:
     enabled: true
     uri: "<ldap-url>"
     start_tls: false
     base: "<ldap-base>"
     attributes:
        uid: "uid"
        mail: "mail"
        name: "givenName"
     filter: "(objectClass=posixAccount)"
```

1. Replace ``<ldap-url>`` with your ldap server url. 
   - Example: ``ldap://ldap01.my-domain.de:389``
2. Replace ``<ldap-base>`` with your ldap base.
   - Example: ``ou=users,dc=my-domain,dc=de``
3. Adapt the ``attributes`` to fit what your LDAP-Server returns for a user
   - Note that all attributes (**uid**, **mail** & **name**) are required to be inside the **attributes:** section.   
   - It's not required that the LDAP-Server returns something for **mail** or **name**. 