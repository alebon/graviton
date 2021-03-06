# Deploying

Graviton supports Cloudfoundry and Docker out of the box.

## Cloudfoundry 

You need to define a mongodb service before pushing. Check ``manifest.yml`` for it's name and
redefine as needed.

Pushing to the cloud will push the bare code and run ``composer install`` on the target. 
Currently cloud installs get populated with mongodb fixtures on each start for ease of deployment.

```bash
cf push graviton
```

## Loading fixtures in cloudfoundry.

Add the connection data from ``vcap_services.mongodb-2.2[0].credentials.url``
to ``app/config/parameters_local.xml`` but replace the ``<ip>:<port>``
part with ``localhost:8001``.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<container xmlns="http://symfony.com/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">
  <parameters>
    <parameter key="mongodb.default.server.uri">mongodb://user:pass@127.0.0.1:8001/db</parameter>
  </parameters>
</container>
```

Open a local connection using the service connector console.  To
do this you will need to have the ``SwisscomPlugin`` installed in
your ``cf`` command. I'm not currently aware of where I can link to
that and your cloudfoundry might not even support it. 

Look at the following two command to get started opening a tunnel
to cf.
``bash
cf help sc
cf env graviton
cf sc connect 8001 <args from env output as needed>
``

Load the fixtures using app/console.

``bash
php app/console doctrine:mongodb:fixtures:load
``

You can also connect to your mongodb with other clients for maintenance
purposes.

## Docker

### Using docker-compose

Install docker-compose (ie. on CoreOS).

```bash
mkdir -p /opt/bin
curl -L https://github.com/docker/compose/releases/download/1.2.0/docker-compose-`uname -s`-`uname -m` > /opt/bin/docker-compose
chmod +x /opt/bin/docker-compose 
```

Run ``docker-compose``.

```bash
docker-compose run --rm install
docker-compose run --rm prepare
sudo mkdir /var/lib/mongodb
sudo chown -f 999:999 /var/lib/mongodb
docker-compose up -d webserver
```

### Create a vendorized image

The following is to be run in a graviton working copy on a PHP build machine.

```bash
composer install
docker-compose build vendorized
```

It may be used as follows without running install or prepare.

```bash
sed -i -e 's/- app$/- vendorized/' docker-compose.yml
docker-compose up -d webserver
```
You will most likely want to override ``/app/app/config/parameters.yml`` for you local
environment. This and other things are best done by managing a local version of
``docker-compose.yml`` for each deploy.


### Manually

```bash
APP_NAME="graviton"

# install deps
docker pull graviton/graviton:latest
docker pull composer/composer:latest
docker pull gravityplatform/php-fpm
docker pull nginx:latest

# create app volume container
docker create --name "${APP_NAME}-app" graviton/graviton:latest noop

# install deps in app volume container
docker run --volumes-from "${APP_NAME}-app" --rm composer/composer install --ignore-platform-reqs

# start fpm 
docker run --detach --name "${APP_NAME}-fpm" --volumes-from "${APP_NAME}-app" gravityplatform/php-fpm

# run nginx in front of all of this
docker run --detach --link "${APP_NAME}-fpm":graviton --name "${APP_NAME}-nginx" --publish 80 nginx:latest
```

This creates the following containers

* ``graviton-app`` volume container containing just the source and vendors
* ``graviton-fpm`` running container that serves graviton using a version of ``php:fpm`` containing a symlink from ``/var/www/html`` to ``/app`` (``/app`` is what ``composer/composer`` expects)
* ``gravtion-nginx`` webserver in front of fpm to expose graviton
