

## FTR-SAM-11 — Student Communication & Services

**FTR-SAM-11-001 — Student Communication and Follow-up**  
Original: FTR025  
Record student phone calls, messages, communication attempts, follow-up remarks, and responsible staff.
  
**FTR-SAM-11-004 — Student Communication Group Membership**  
Original: FTR084  
Track student membership in official WhatsApp, Facebook, and other approved communication groups.

**FTR-SAM-11-005 — Academic Alerts and Notifications**  
Original: FTR085  
Support operational alerts for pending academic actions, schedule changes, examinations, and student activities.

---
Module: Students & Academy

FR-001 — Student Communication and Follow-up

The system shall allow users to record student communication activities, including phone calls, messages, communication attempts, follow-up remarks, communication dates, and responsible staff members.

The system shall also allow users to view relevant student information during communication and follow-up, including:
- Student enrollment and course information.
- Outstanding payment dues.
- Academic progress and course completion status.
- Certificate preparation and issuance status.
FR-002 — Student Communication Group Membership Management

The system shall allow users to add or remove students from official WhatsApp, Facebook, and other institution-approved communication groups and maintain their group membership status.

FR-003 — Academic and Student Status Alerts

The system shall support operational alerts and notifications for important student-related events, including:
- Payment Due: Notify responsible staff when a student has outstanding payments.
- Course Completion: Notify responsible staff when a student completes a course.
- Certificate Status: Notify responsible staff when a student's certificate is prepared, issued, or ready for collection.
- Pending Academic Actions: Notify responsible staff about incomplete academic requirements.
- Schedule Changes: Notify relevant staff about class, batch, or examination schedule changes.
- Examinations: Notify responsible staff about upcoming or completed examinations.
- Student Activities: Notify responsible staff about relevant student-related activities.
The system shall allow users to view these notifications and access the corresponding student records.
Business Rules
BR-001: Communication records must be associated with the relevant student.

BR-002: Student payment, academic, and certificate information must be accessible from the relevant student record.

BR-003: A student may belong to multiple official communication groups.

BR-004: Removing a student from a communication group must not delete previous membership history.

BR-005: Notifications must be associated with the relevant student or academic activity whenever applicable.

BR-006: Student payment, course completion, and certificate status notifications must reflect the actual status recorded in their respective modules.

BR-007: Notifications must provide access to the relevant student record whenever applicable.

BR-008: Updating student information must not delete previous communication or notification history.
Acceptance Criteria
AC-001: Users can record student communication and follow-up activities.

AC-002: Users can view payment dues, academic progress, and certificate status from student records.

AC-003: Users can add students to official communication groups.

AC-004: Users can remove students from communication groups while preserving membership history.

AC-005: The system generates or records notifications when students have outstanding payment dues.

AC-006: The system generates or records notifications when students complete courses.

AC-007: The system generates or records notifications when certificates are prepared, issued, or ready for collection.

AC-008: The system supports notifications for pending academic actions, schedule changes, examinations, and student activities.

AC-009: Users can view student-related notifications and navigate to the corresponding student records.

AC-010: Student communication and notification histories remain accessible.

Important distinction: Payment dues, course completion, and certificate issuance should be calculated and maintained by their respective Finance, Academy, and Certificate features. This feature should display and notify users about those events rather than duplicate their business logic.