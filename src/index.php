<?php
/**
 * Zentraler HTTP-Request-Handler
 */
require(dirName(__FILE__).'/WEB-INF/config.php');

FrontController ::processRequest();
