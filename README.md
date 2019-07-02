# docker4drupal
Docker setup on local machine (env)

In this docker image , docker.compose.yml file I used server for local env. is "192.168.1.198"
Generally by default is used as http://drupal.docker.localhost:8000 for apache/nginx server.

Step 1. Create a drupal codebase using composer. 
        github repo . https://github.com/drupal-composer/drupal-project
        
        composer create-project drupal-composer/drupal-project:8.x-dev some-dir --no-interaction
        Need to run above command for drupal on localmachine.
        
Step 2. Clone the drupal4docker github repo on your system . 
        https://github.com/wodby/docker4drupal
        
Step 3. Move the docker4drupal clone to your local repo which is created earlier using composer.

Step 4. Change your docker-compose.yml file or .env file for configuration.

Step 5. Go to project dir. and run docker-compose up -d 
        It will create container and hit the link you setup on your configration file and
        then drupal will install. 
        
 
 
Note - Here i used MariaDb so at the time of installation of drupal,
       Enter mariadb instead of localhost.
       and i used a port 192.168.1.198 instead of http://drupal.docker.localhost:8000
       so it can be replace in docker-compose.yml file or .env file 
 
        
        


