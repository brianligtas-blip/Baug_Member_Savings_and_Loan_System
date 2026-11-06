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

        .notice-intro {
            background: #edf7f0;
            border-left: 4px solid #176b3a;
            padding: 15px;
            border-radius: 5px;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 30px;
        }

        .back-button,
        .understand-button {
            display: inline-block;
            padding: 11px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
        }

        .back-button {
            color: #176b3a;
            background: #edf7f0;
            border: 1px solid #176b3a;
        }

        .understand-button {
            color: white;
            background: #176b3a;
            border: none;
        }

        .back-button:hover {
            background: #dcefe2;
        }

        .understand-button:hover {
            background: #10532c;
        }

        @media (max-width: 600px) {
            .container {
                padding: 22px;
            }

            .actions {
                flex-direction: column;
            }

            .actions a {
                text-align: center;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Privacy Notice</h1>

    <p class="updated">
        BAUG Member Savings and Loan System<br>
        Last Updated: November 2026
    </p>

    <div class="notice-intro">
        <strong>Your privacy matters to us.</strong>
        This notice explains how the BAUG Member Savings and Loan
        System collects, uses, stores, and protects personal
        information in accordance with the Data Privacy Act of 2012
        (Republic Act No. 10173).
    </div>

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
        BAUG will retain members' personal information, savings records,
        loan records, and transaction history only for as long as necessary
        to fulfill their intended purposes and applicable legal,
        accounting, and organizational requirements.
    </p>

    <p>
        Records will be periodically reviewed to determine whether
        continued retention is necessary. When information is no longer
        required and there is no legal reason to retain it, it will be
        securely disposed of or anonymized.
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
        For questions, requests, or concerns regarding the
        collection, processing, storage, or protection of your
        personal information, you may contact BAUG through
        its official email address:
    </p>

    <p>
        <strong>Official BAUG Email:</strong>
        <a href="mailto:ligtasbrian659@gmail.com">
            ligtasbrian659@gmail.com
        </a>
    </p>

    <p>
        Members may use this email address to submit privacy-related
        inquiries, request access to their personal information,
        or raise concerns about how their data is handled.
        </p>


    <h2>8. Consent</h2>
    <p>
        During registration, you will be asked to confirm that
        you have read and understood this Privacy Notice.
        Your consent should be recorded by the system together
        with the date and time of your registration.
    </p>

    <div class="actions">
        <a href="register.php" class="back-button">
            Back to Registration
        </a>

        <a href="register.php#privacy-consent"
           class="understand-button">
            I Have Read the Privacy Notice
        </a>
    </div>

</div>

</body>
</html>