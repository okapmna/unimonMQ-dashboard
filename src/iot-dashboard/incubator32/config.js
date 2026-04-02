// config.js

const mqttConfig = {
    // Konfigurasi Broker
    host: "test.mosquitto.org",
    port: 8080,
    username: "", 
    password: "", 
    useSSL: false,

    // Konfigurasi Topik
    topics: {
        // Topic untuk Subscribe (Menerima Data JSON)
        subscribe: {
            data: "incubator32/data" // Topik tunggal untuk data JSON (temp & humi)
        },
        
        // Topic untuk Publish
        publish: {
            control: "incubator32/con",
        }
    }
};