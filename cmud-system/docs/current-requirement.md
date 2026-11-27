


requirement for student bill
==============================

Student Bill -- done
    -create a student payment menthod  currently for cash and mobile banking
              - Two types of payment  a. tution b. other expenses
              - A button in header open a dialog box that create all type of payment(tution and other)
              - The payment creation has two type a. cash and b. mobile banking 
    -searchpayment record
            -user can seartch payment record by name or student id and particular date
            -for default on the screen it show latest 15 record + a pagination
    -Update student payments to support Cash and Mobile Banking (bKash). For bKash, require and store the payer’s phone number and transaction ID. Since the bKash API is not implemented, save those payments with an unverified/pending verification status; do not mark them verified. Keep the design extendable to other mobile banking providers later. Follow the existing module architecture and conventions, use Form Requests, Services, and Pest tests, and do not run database migrations.


    - Now i am planing  to update the ui(don't  implement backend) on the /billings/student-bill on page header add a button that open a dialog box that create both payment other expenses(billing + payment same time), the both form take student id with verification staus, show payment method currently we support mobile banking and cash, for mobile banking we support bkash(check box). for mobile bangkin it take payer number, transection id  with verification status(after enring the trasection id it auto all the api then verify it) currenlt just add unverified. then crearte the bill, and we can print any bill. now analyze i what i want then updaqte the UI. 

    - Find the billing portion only only contain search bar(take student id or name), date with search button with get information for particular branch data(super admin can have all branch data)

    - User policy a particular branch employee only can access billing for aprticular branch billing data except the super admin(have full access)


    - remove Student charge, select on mobile banking on the bkash box the add transection id inputfileld + a pill for status(varified or unvaried) i hope u understand


Intitude Other Bill -- done
I want to add another page under the billing "Institude Other Bill"
this page purpose is creating custom bill from the institude(the bill actually paid by other body). 
   - there is a table for institude other income bill
   - just make api for crud for that table
   - and make ui the can create a custom input and make custon json + add a total bill and sent to api
   - user create a fileld for the bill item  and price 
   - table has column to store json object so ui geenrate the json object and filled to table with total billed
   - on ui during the bill creation ui capable of calculation the total bill 
   - the bill can be printable with well organize foramte
   - page contain all other bill record with pagination
   - user can search old bill by a date

now make the backend and frontend, for the backend in finance module (use a particular folder to put all files related to backend on every folder service, controller,request etc..), the folder name should be InstitudeOtherBill
the ui should have three portion header - contain a button that open a dialog box that create bill, search/filder portion that that contain a search bar(take the bliing id) and a date + button strigger the search, the registry show the billing list with view and printable option with this user can print the bill properly.

Academy

Certificate mangement
 - this page only job is findout eligible candidate for the cerficate and 
  chnage the status like issued, delivered, 
 - maintain the application for reissue certificate,




Curse Page
 - 

 
Examcontroll page
 - user can search students by name,id
 - user can find students list for a particular curse batch
 - admin user can update the student exam status like eligible for exam, sbmitted paper, pending for result, passed, failed, retake etc..
 - admin can't issue the exam eligibility if the student didn't complete the curse duration.(batch has curse duration)




 Test Case scenerio for student life cycle:
 ============================================
 1. a stedent enrolled a advance curse has  semister to finish
 2. a student 1 semister and eligible for 1 semister certificate
 3. a student finished 3 semister and eleigible for final certificate
 4. a student enrolled a curse bt finished curse the exceed the palnned duration
 5. a student enrolled a curse finishe in the vilidation period
 6. a studet exceed the validation period
 7. 




