# UNIMONMQ - Dashboard

A Simple IoT Dashboard for monitoring and controlling devices via MQTT.

## Key Features

- **Real-time Monitoring**: Direct control and monitoring of devices (Incubator, Smartlamp, etc.).
- **Background MQTT Logger**: Automatically records sensor data to the MariaDB database even when the dashboard is closed in the browser.
- **Data Aggregation**: Calculates Average, Median, High, and Low values every 5 minutes for sensor devices.
- **Statistical Visualization**: Interactive Line Charts to monitor historical and real-time trends for temperature and humidity.
- **Dockerized**: Easy setup using Docker & Docker Compose.

## Setup Guide

Follow these steps to set up the dashboard in your local environment:

### 1. Prerequisites
Ensure you have **Docker** and **Docker Compose** installed on your system.

### 2. Environment Configuration
Copy the `.env.example` file to `.env` inside the `app` directory:
```bash
cp app/.env.example app/.env
```
*Make sure the database configuration in `.env` matches the one in `docker-compose.yml`.*

### 3. Run Containers

Development (hot reload for backend, sync for views/static files):
```bash
docker compose watch
```

Production:
```bash
docker compose -f docker-compose.yml up -d --build
```
Use the explicit `-f docker-compose.yml` in production so `docker-compose.override.yml` (development mode) is not loaded.

### 4. Database Initialization
1. Open **phpMyAdmin** at `http://localhost:8082`.
2. Log in with user: `root` and password: `rootpassword` (or as configured in `docker-compose.yml`).
3. Import the `database/unimq.sql` file into the `unimq` database.
   *Note: Ensure the `device_logs` table is present to enable history features.*

### 5. Access the App
Open your browser and navigate to:
`http://localhost:8180`

## Monitoring Logs and Background Worker
To view the application and MQTT worker activity in real-time, use the following command:
```bash
docker logs -f unimon_app
```

## Adding a New Device Type

See [NEW_DEVICE_TYPE_GUIDE.md](NEW_DEVICE_TYPE_GUIDE.md) for step-by-step instructions on how to add support for new device types (e.g., thermostat, sensor, actuator) to the dashboard.

## Preview

<img width="1587" alt="Main Dashboard" src="preview/main_dashbboard.png" />
<img width="1587" alt="Add New Device" src="preview/add_new_dev.png" />
<img width="1587" alt="Incubator Preview" src="preview/incubator_preview.png" />

## Contributors

- TOBIAS DON BOSCO - [@tobiasdonb](https://github.com/tobiasdonb)
- Oka Pmna - [@okapmna](https://github.com/okapmna)
- IDA BAGUS WILLI PARMITA - [@WILIOP-666](https://github.com/WILIOP-666)
- Maria M - [@nmau080-eng](https://github.com/nmau080-eng)
- Doel_176 - [@doelCR7](https://github.com/doelCR7)
