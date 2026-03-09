/*
SQLyog Community v13.1.9 (64 bit)
MySQL - 5.6.43 
*********************************************************************
*/
/*!40101 SET NAMES utf8 */;

create table `item_master` (
	`id` int (11),
	`item_name` varchar (300),
	`category_id` int (11),
	`subcategory_id` int (11),
	`BrandID` int (11),
	`mrp` double ,
	`tax_p` double ,
	`saleprice` double ,
	`dis_p` double ,
	`hsn` varchar (60),
	`size_dimension` varchar (45),
	`weight` Decimal (11),
	`color` varchar (45),
	`packingtype` varchar (30),
	`packingtime` int (11),
	`description` text ,
	`status` varchar (60)
); 
