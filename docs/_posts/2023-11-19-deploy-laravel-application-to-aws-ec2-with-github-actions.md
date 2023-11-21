---
title: Deploy Laravel application to AWS EC2 with GitHub Actions
layout: post
---

In the past years, I deployed a couple of Laravel apps to production using AWS and Github, and from time to time I find myself looking into other projects to remember which steps I took to set up the server, GitHub, the database, etc. Well, it can be a very repetitive task so I decided to document the steps in this post and share it with the world.

Depending on the size and requirements of the application, you have several options to deploy a Laravel application to a production environment; you may choose [AWS Elastic Beanstalk (ELB)](https://aws.amazon.com/elasticbeanstalk/), [Amazon Elastic Kubernetes Service (EKS)](https://aws.amazon.com/eks/), [Amazon EC2](https://aws.amazon.com/ec2/), [AWS Lightsail](https://aws.amazon.com/lightsail/), etc... The list of options can be very extensive. This tutorial is focused on deploying a small app to a single EC2 instance that bundles all the necessary pieces to run a Laravel application such as a web server and database which in this case will be [Nginx](https://www.nginx.com/) and [Postgres](https://www.postgresql.org/) respectively.

Even though I used AWS as a cloud provider, these steps can be easily reproduced on any other provider like DigitalOcean, GCP, Azure, or Linode.

### Prerequisites
Before diving into the deployment, ensure you have the following:

* **GitHub Repository:** Your Laravel application hosted on GitHub.
* **AWS Account**:  Your account with permission to manager EC2.

### Create the EC2 Instance
The first thing we need to do is to log in into the AWS console and navigate to the EC2 dashboard:
![AWS console]({{ 'assets/images/posts/deploy-laravel-application-to-a-single-server-with-ci-cd/1.png' | relative_url }})

Now, click on the launch instance button:
![Launch button]({{ 'assets/images/posts/deploy-laravel-application-to-a-single-server-with-ci-cd/2.png' | relative_url }})

Define the name of your instance and choose an operational system. On this tutorial we are going to use Ubuntu 22.04 LTS.
![Define instance]({{ 'assets/images/posts/deploy-laravel-application-to-a-single-server-with-ci-cd/3.png' | relative_url }})

Create a new key pair to the instance that will be used later on to connect to the instance:
![Key pair button]({{ 'assets/images/posts/deploy-laravel-application-to-a-single-server-with-ci-cd/4.png' | relative_url }})

It's very important to save this file to a secure place. If you lose it, you are not going to be able to download it again.
![Key pair form]({{ 'assets/images/posts/deploy-laravel-application-to-a-single-server-with-ci-cd/5.png' | relative_url }})

Now, let's define the network settings. We are to create a new security group that will allow traffic for SSH, HTTP, and HTTPS from anywhere
![Network settings]({{ 'assets/images/posts/deploy-laravel-application-to-a-single-server-with-ci-cd/6.png' | relative_url }})

Click on the launch button. You should see a success message and will be able to connect to the instance
![Network settings]({{ 'assets/images/posts/deploy-laravel-application-to-a-single-server-with-ci-cd/7.png' | relative_url }})

Now, connect to the server and update the system dependencies by running the following command:
```bash
sudo apt update
sudo apt upgrade
```
### Install PHP
Run the following command to install PHP with Laravel-required extensions:
```bash
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install -y  php8.2 php8.2-mbstring php8.2-bcmath php8.2-xml php8.2-zip php8.2-curl php8.2-fpm php8.2-pgsql
```

Test if the installation succeeded by checking the PHP version:
```bash
php -v
```

You should see the following output:
![PHP version output]({{ 'assets/images/posts/deploy-laravel-application-to-a-single-server-with-ci-cd/8.png' | relative_url }})


Install Composer to manage the application dependencies:
```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"
sudo mv composer.phar /usr/local/bin/composer
```

Test if the installation succeeded by checking the Composer version:
```bash
composer -v
```

You should see the following output:
![Composer version output]({{ 'assets/images/posts/deploy-laravel-application-to-a-single-server-with-ci-cd/9.png' | relative_url }})

### Install Nginx
By default the Ubuntu server comes with the Apache web server, so we first need to remove it to install Nginx.
```bash
sudo apt remove apache2
```

Now install Nginx:
```bash
sudo apt install nginx
```

If everything goes well, you will be able to access the Nginx default page by typing the public IP of your server in the browser and see the following screen:
![Nginx welcome page]({{ 'assets/images/posts/deploy-laravel-application-to-a-single-server-with-ci-cd/10.png' | relative_url }})

### Install the Postgres
This step is considered optional because you can opt to use a managed database like [AWS RDS](https://aws.amazon.com/rds) instead of running on your own server. As a general rule of thumb, generally, we don't install everything in one server but it can okay depending on your workload.  [AWS RDS](https://aws.amazon.com/rds) is included in the AWS free tier package so you can start using it for free.

Install the database server:
```bash
sudo apt install postgresql
```

Check if the installation went well by looking into the server status.
```bash
sudo apt install postgresql
```

You should see the following output:
![PostgresSQL process status]({{ 'assets/images/posts/deploy-laravel-application-to-a-single-server-with-ci-cd/11.png' | relative_url }})


Create your schema and update the default user password:
```bash
sudo -u postgres psql template1
```
```sql
CREATE DATABASE laravel;
ALTER USER postgres with encrypted password 'laravel';
```

### Setup application
Now we need to zip the application on your local PC and send it to the server. We can do this using scp, which is a tool that allows you to securely transfer files to the server.

Let's first zip the application:
```bash
zip -r app.zip PATH_TO_YOUR_APP
```

Now we can send it to the server. We are going to need the SSH private key that we got when we created our EC2 instance to do this. To be able to use the key we first need to give it the proper permission otherwise, we are going to get a "bad permissions" error.
This command will read-only permission to your key:
```bash
chmod 400 PATH_TO_YOUR_KEY
```

Now we can send the zip file:
```bash
scp -i PATH_TO_YOUR_KEY app.zip ubuntu@YOUR_EC2_PUBLIC_IP:~/
```

Let's connect to the server using SSH to set up the application:
```bash
ssh -i PATH_TO_YOUR_KEY ubuntu@YOUR_EC2_PUBLIC_IP
```

Unzip the app:
```bash
sudo apt install -y unzip
unzip app.zip -d .
```

Move it to the web sites folder:
```bash
sudo mv app /var/www/app
```

Change the ownership of the app folder to the Ubuntu default webserver user and update the storage and bootstrap folder permissions
```bash
sudo chown -R $USER:www-data app
sudo chmod -R 775 app/storage
sudo chmod -R 775 app/bootstrap/cache
```

Update the .env file with the production settings and database credentials. Your .env file should look like this:
```
APP_NAME="YOUR APP NAME"
APP_ENV=production
APP_KEY=YOUR_APP_KEY
APP_DEBUG=false
APP_URL=http://YOUR_EC2_PUBLIC_IP

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=pgsql    
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=postgres
DB_PASSWORD=laravel

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120
```

Migrate the database:
```
php artisan migrate
```

You should see the following output:
![Database migration output]({{ 'assets/images/posts/deploy-laravel-application-to-a-single-server-with-ci-cd/12.png' | relative_url }})


Update Nginx default site config file /etc/nginx/sites-enabled/default with the following content.
```
server {
         listen 80 default_server;
         listen [::]:80 default_server;
         server_name _;
         root /var/www/app/public;

         access_log /var/log/nginx/YOUR_APP_NAME.com-access.log;
         error_log  /var/log/nginx/YOUR_APP_NAME.com.log error;
         index index.html index.htm index.php;

         location / {
              try_files $uri $uri/ /index.php$is_args$args;
         }

         location ~ \.php$ {
            fastcgi_split_path_info ^(.+\.php)(/.+)$;
            fastcgi_pass unix:/var/run/php8.2-fpm-YOUR_APP_NAME.sock;
            fastcgi_index index.php;
            include fastcgi.conf;
        }
}
```

Configure the [PHP FPM](https://www.php.net/manual/en/install.fpm.php) pool for your app with the following content:

```bash
nano /etc/php/8.2/fpm/pool.d/YOUR_APP_NAME.conf
```
```
[YOUR_APP_NAME]
user = www-data
group = www-data
listen = /var/run/php8.2-fpm-YOUR_APP_NAME.sock
listen.owner = www-data
listen.group = www-data
php_admin_value[disable_functions] = exec,passthru,shell_exec,system
php_admin_flag[allow_url_fopen] = off
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.process_idle_timeout = 10s
```

If you access your server on the browser again you should be able to see your initial page. On my app I've used [Laravel Breeze](https://laravel.com/docs/10.x/starter-kits) starter kit to create the application so I have some basic features like sign up, sign in, and profile update to test.
### Setup GitHub Action
Now that everything is settled on the server,  we can create a [GitHub Action](https://docs.github.com/actions) to deploy the application every time the main branch is updated.

The first thing we need to do is set up the secrets on the GitHub repository. Go to **Settings** and then on the **Secrets and Actions** section, click on **Actions**
![GitHub settings for secrets]({{ 'assets/images/posts/deploy-laravel-application-to-a-single-server-with-ci-cd/13.png' | relative_url }})

We are going to create three secrets: 

PRODUCTION_SERVER_HOST: the public IP of your EC2 instance.
![GitHub settings for secrets]({{ 'assets/images/posts/deploy-laravel-application-to-a-single-server-with-ci-cd/14.png' | relative_url }})

PRODUCTION_SERVER_USERNAME: the user name of your server instance. If you used a Ubuntu server, the user name should be "ubuntu".
![GitHub settings for secrets]({{ 'assets/images/posts/deploy-laravel-application-to-a-single-server-with-ci-cd/15.png' | relative_url }})

PRODUCTION_SERVER_SSH_KEY: the content of the private key that got when you created the server.
![GitHub settings for secrets]({{ 'assets/images/posts/deploy-laravel-application-to-a-single-server-with-ci-cd/16.png' | relative_url }})

Now, create a file called **deploy.yml** on the **.github/workflows** folder with the following content:
[action content]({{ 'assets/deploy.yml' | relative_url }})

This action will do the following:
* Checkout to the main branch
* Install PHP
* Install the Composer dependencies
* Install Node.js
* Install Node dependencies
* Build the front-end assets
* Upload the files to the server and place it in the temporary folder
* Setup the new version on the server remove the old version and migrate the database

That's it, are done! 
### Final considerations
In this tutorial I've used a very simplified configuration for Nginx, PHP FPM, and Postgres, you can finetune those configurations by diving into the documentation of each software.
In addition, you can improve the deploy action by adding a step to run the automated tests of your application to make sure you are not deploying a broken version.

Good luck on your journey through the cloud world! 🎉🎉🎉
