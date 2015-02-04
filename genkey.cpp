#include <algorithm>
#include <fstream>
#include <iostream>
#include <vector>
#include <ctime>

using namespace std;

#define MIN_PRIME 1000
#define MAX_PRIME 10000
#define K 10

bool erathostenes[MAX_PRIME];
vector<int> primes;
long long n;
int k;
vector<long long> s;
vector<long long> v;

bool check4r3(int p) {
    return 0 == (p - 3) % 4;
}

vector<int> buildPrimes() {
    vector<int> primes;

    for (int i = 2; i < MAX_PRIME; i++) {
        if (false == erathostenes[i]) {
            if (i >= MIN_PRIME && true == check4r3(i)) {
                primes.push_back(i);
            }

            for (int j = 2 * i; j < MAX_PRIME; j += i) {
                erathostenes[j] = true;
            }
        }
    }

    return primes;
}

long long compN() {
    int p = primes[rand() % primes.size()];

    int q = primes[rand() % primes.size()];
    while (p == q) {
        q = primes[rand() % primes.size()];
    }

    return 1LL * p * q;
}

vector<long long> buildS() {
    vector<long long> s;

    for (int i = 0; i < k; i++) {
        s.push_back(rand() % n);
    }

    return s;
}

vector<long long> buildV() {
    vector<long long> v;

    for (unsigned i = 0; i < s.size(); i++) {
        // v.push_back((1LL * (rand() % 2 ? -1 : 1) * s[i] * s[i]) % n);
        v.push_back((s[i] * s[i]) % n);
    }

    return v;
}

void writePrivate() {
    ofstream fout("ffs_key.private");

    for (unsigned i = 0; i < s.size(); i++) {
        fout << s[i] << (i < s.size() - 1 ? " " : "");
    }

    fout.close();
}

void writePublic(char* username) {
    ofstream fout("ffs_key.public");

    fout << username << " ";
    fout << n << " ";

    for (unsigned i = 0; i < v.size(); i++) {
        fout << v[i] << (i < v.size() - 1 ? " " : "");
    }

    fout.close();
}

int main(int argc, char* argv[]) {
    if (argc < 2) {
        cout << "Username not provided.\n";
        cout << "Failed.\n";

        return 0;
    }

    cout << "Generating keys...\n";

    srand(time(0));

    primes = buildPrimes();
    n = compN();
    k = K;
    s = buildS();
    v = buildV();

    cout << "Writing private key...\n";
    writePrivate();

    cout << "Writing public key...\n";
    writePublic(argv[1]);

    cout << "Done.\n";

    return 0;
}
