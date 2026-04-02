# UNIMONMQ - Dashboard

Simple IoT Dashboard for MQTT.

## Setup Guide

Follow these steps to set up the dashboard locally using Docker:

1. Prerequisites: Ensure you have Docker and Docker Compose installed on your system.
2. Environment Configuration: Copy the `.env.example` file to `.env` inside the `src` directory.
   ```bash
   cp src/.env.example src/.env
   ```
3. Build and Run: Start the containers using Docker Compose.
   ```bash
   docker-compose up -d --build
   ```
4. Access the App: Once the containers are running, access the dashboard at:
   `http://localhost:8080`
5. Database Setup: Open phpMyAdmin at `http://localhost:8082` and import the database schema from `database/unimq.sql`.

## Preview

<img width="1587" alt="Main Dashboard" src="preview/main_dashbboard.png" />
<img width="1587" alt="Add New Device" src="preview/add_new_dev.png" />
<img width="1587" alt="Incubator Preview" src="preview/incubator_preview.png" />

## Contributors

- TOBIAS DON BOSCO - [@tobiasdonb](https://github.com/tobiasdonb)
- Oka Pmna - [@okapmna](https://github.com/okapmna)
- IDA BAGUS WILLI PARMITA - [@WILIOP-666][def]
- Maria M - [@nmau080-eng](https://github.com/nmau080-eng)
- Doel_176 - [@doelCR7](https://github.com/doelCR7)
