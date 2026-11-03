<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Notice | BAUG Member Savings and Loan System</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px 15px;
            font-family: Arial, sans-serif;
            background: #f4f7f4;
            color: #333;
            line-height: 1.7;
        }

        .container {
            max-width: 850px;
            margin: auto;
            padding: 35px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
        }

        h1, h2 {
            color: #176b3a;
        }

        h1 {
            margin-top: 0;
            text-align: center;
        }

        h2 {
            margin-top: 25px;
            font-size: 20px;
        }

        .updated {
            text-align: center;
            color: #666;
            font-size: 14px;
        }

        .back-button {
            display: inline-block;
            margin-top: 25px;
            padding: 10px 18px;
            color: white;
            background: #176b3a;
            text-decoration: none;
            border-radius: 6px;
        }

        .back-button:hover {
            background: #10532c;
        }

        @media (max-width: 600px) {
            .container {
                padding: 22px;
            }
        }
    </style>
</head>

<body>
<div class="container">

    <h1>Privacy Notice</h1>

    <p class="updated">
        BAUG Member Savings and Loan System
    </p>

    <p>
        The BAUG Member Savings and Loan System respects the privacy
        of its members. This Privacy Notice explains what personal
        information the system collects, why it is collected, and
        how it is handled in accordance with the Data Privacy Act
        of 2012 (Republic Act No. 10173).
    </p>

    <h2>1. Personal Information We Collect</h2>
    <p>The system may collect the following information:</p>
    <ul>
        <li>Full name and member ID number</li>
        <li>Date of birth and age</li>
        <li>Home address</li>
        <li>Email address and username</li>
        <li>Password and security question answers</li>
        <li>Member category and occupation</li>
        <li>Monthly savings target</li>
        <li>Savings, loan, and transaction records</li>
        <li>Privacy consent and its date and time</li>
    </ul>

    <h2>2. Purpose of Collecting Information</h2>
    <p>
        Personal information is collected and processed for
        membership registration, member identification, account
        authentication, savings and loan management, account
        recovery, transaction recording, and necessary member
        communication.
    </p>

    <h2>3. How We Protect Your Information</h2>
    <p>
        The system uses password hashing for passwords and security
        answers. Access to member information should be limited to
        authorized users. Appropriate security measures should be
        maintained to help prevent unauthorized access, disclosure,
        alteration, or loss of personal information.
    </p>

    <h2>4. Sharing of Information</h2>
    <p>
        Personal information should only be accessed or shared by
        authorized persons when necessary for legitimate system
        operations or when required by applicable law. It should
        not be disclosed for unrelated purposes without a valid
        legal basis.
    </p>

    <h2>5. Data Retention</h2>
    <p>
        Personal information will be retained only for as long as
        necessary for the purposes for which it was collected,
        subject to applicable legal, accounting, and organizational
        requirements. The organization should establish and follow
        an appropriate retention and secure disposal policy.
    </p>

    <h2>6. Your Data Privacy Rights</h2>
    <p>
        Under the Data Privacy Act of 2012, data subjects have
        applicable rights regarding their personal information.
        These may include the right to be informed, access,
        object, rectify, erase or block, and seek redress, subject
        to the conditions and limitations of the law.
    </p>

    <h2>7. Contact Information</h2>
    <p>
        For questions, requests, or concerns about the handling
        of your personal information, please contact the
        authorized BAUG representative or designated privacy
        contact.
    </p>

    <h2>8. Consent</h2>
    <p>
        By checking the Privacy Consent box during registration,
        you confirm that you have read this notice and agree to
        the collection and processing of your personal information
        for the stated purposes, subject to applicable law.
    </p>

    <a href="register.php" class="back-button">
        Back to Registration
    </a>

</div>
</body>
</html>